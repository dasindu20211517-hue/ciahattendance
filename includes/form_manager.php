<?php
/**
 * Form Link Manager - Manages form generation and submissions
 * 
 * Handles:
 * - Generating unique form links
 * - Managing form submissions
 * - Tracking form data
 * - Auto-converting to leave/OT requests
 */

require_once __DIR__ . '/../database.php';

class FormLinkManager {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Generate a unique form link
     */
    public function generateFormLink(string $formType, ?int $userId = null, ?array $formData = null, ?int $expireDays = 30): string {
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expireDays} days"));
        $formDataJson = $formData ? json_encode($formData) : null;
        
        $stmt = $this->db->prepare("
            INSERT INTO form_links (token, form_type, user_id, expires_at, form_data)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$token, $formType, $userId, $expiresAt, $formDataJson]);
        
        return $token;
    }
    
    /**
     * Get form link details
     */
    public function getFormLink(string $token): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM form_links 
            WHERE token = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > datetime('now'))
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['form_data']) {
            $result['form_data'] = json_decode($result['form_data'], true);
        }
        
        return $result ?: null;
    }
    
    /**
     * Submit form data
     */
    public function submitForm(string $token, array $data, ?string $ipAddress = null, ?string $userAgent = null): array {
        try {
            $formLink = $this->getFormLink($token);
            if (!$formLink) {
                throw new Exception('Invalid or expired form link');
            }
            
            $userId = $data['user_id'] ?? $formLink['user_id'];
            if (!$userId) {
                throw new Exception('User ID is required');
            }
            
            // Store submission
            $stmt = $this->db->prepare("
                INSERT INTO form_submissions (form_link_id, user_id, form_type, submission_data, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $formLink['id'],
                $userId,
                $formLink['form_type'],
                json_encode($data),
                $ipAddress,
                $userAgent
            ]);
            
            $submissionId = $this->db->lastInsertId();
            
            // Convert to leave or OT request
            $result = $this->processSubmission($formLink['form_type'], $userId, $data);
                if (isset($result['error'])) {
                    throw new Exception($result['error']);
                }
            
            // Send WhatsApp confirmation
            $this->sendSubmissionConfirmation($userId, $formLink['form_type'], $data, $result);
            
            return [
                'success' => true,
                'submission_id' => $submissionId,
                'message' => 'Form submitted successfully',
                'data' => $result
            ];
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process form submission and create leave/OT record
     */
    private function processSubmission(string $formType, int $userId, array $data): array {
        try {
            if ($formType === 'leave') {
                return $this->processLeaveSubmission($userId, $data);
            } elseif ($formType === 'overtime') {
                return $this->processOvertimeSubmission($userId, $data);
            } else {
                throw new Exception('Unknown form type: ' . $formType);
            }
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Process leave form submission
     */
    private function processLeaveSubmission(int $userId, array $data): array {
        $stmt = $this->db->prepare("
            INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $userId,
            $data['leave_type'] ?? 'Annual Leave',
            $data['start_date'],
            $data['end_date'],
            $data['reason'] ?? null
        ]);
        
        return [
            'type' => 'leave',
            'leave_id' => $this->db->lastInsertId(),
            'status' => 'pending'
        ];
    }
    
    /**
     * Process overtime form submission
     */
    private function processOvertimeSubmission(int $userId, array $data): array {
        $stmt = $this->db->prepare("
            INSERT INTO overtime (user_id, ot_date, hours, reason, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $userId,
            $data['ot_date'],
            $data['hours'],
            $data['reason'] ?? null
        ]);
        
        return [
            'type' => 'overtime',
            'ot_id' => $this->db->lastInsertId(),
            'status' => 'pending'
        ];
    }
    
    /**
     * Get form submissions for a link
     */
    public function getFormSubmissions(string $token, int $limit = 50): array {
        $formLink = $this->getFormLink($token);
        if (!$formLink) return [];
        
        $stmt = $this->db->prepare("
            SELECT * FROM form_submissions 
            WHERE form_link_id = ?
            ORDER BY submitted_at DESC
            LIMIT ?
        ");
        $stmt->execute([$formLink['id'], $limit]);
        
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function($sub) {
            $sub['submission_data'] = json_decode($sub['submission_data'], true);
            return $sub;
        }, $submissions);
    }
    
    /**
     * Deactivate form link
     */
    public function deactivateFormLink(string $token): bool {
        $stmt = $this->db->prepare("UPDATE form_links SET is_active = 0 WHERE token = ?");
        return $stmt->execute([$token]);
    }
    
    /**
     * Get form link stats
     */
    public function getFormStats(string $token): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(fs.id) as total_submissions,
                form_links.form_type,
                form_links.created_at,
                form_links.expires_at
            FROM form_links
            LEFT JOIN form_submissions fs ON form_links.id = fs.form_link_id
            WHERE form_links.token = ?
            GROUP BY form_links.id
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    /**
     * Generate form URL
     */
    public static function getFormURL(string $token, string $baseURL = ''): string {
        if (!$baseURL) {
            $baseURL = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
                $scriptDirectory = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')));
                if ($scriptDirectory !== '/' && $scriptDirectory !== '.') {
                    $baseURL .= rtrim($scriptDirectory, '/');
                }
        }
        return $baseURL . '/form.php?token=' . urlencode($token);
    }
    
    /**
     * Send WhatsApp confirmation after form submission
     */
    private function sendSubmissionConfirmation(int $userId, string $formType, array $data, array $result): void {
        try {
            // Get user details
            $stmt = $this->db->prepare("SELECT whatsapp_number, COALESCE(NULLIF(calling_name,''), name) as name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || empty($user['whatsapp_number'])) {
                return; // No WhatsApp number available
            }
            
            require_once __DIR__ . '/../config_whatsapp.php';
            require_once __DIR__ . '/../whatsapp_web_sender.php';
            
            $typeLabel = $formType === 'leave' ? 'Leave' : 'Overtime';
            $typeEmoji = $formType === 'leave' ? '🏖️' : '⏰';
            $successEmoji = '✅';
            
            $message = "$successEmoji *{$typeLabel} Request Submitted*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "Hi {$user['name']}! 👋\n\n";
            $message .= "Your *{$typeLabel} Request* has been submitted successfully!\n\n";
            
            if ($formType === 'leave') {
                $message .= "📋 *Request Details:*\n";
                $message .= "• *Type:* " . ($data['leave_type'] ?? 'Annual Leave') . "\n";
                $message .= "• *From:* " . date('M j, Y', strtotime($data['start_date'])) . "\n";
                $message .= "• *To:* " . date('M j, Y', strtotime($data['end_date'])) . "\n";
                if (!empty($data['reason'])) {
                    $message .= "• *Reason:* " . $data['reason'] . "\n";
                }
            } else {
                $message .= "📋 *Request Details:*\n";
                $message .= "• *Date:* " . date('M j, Y', strtotime($data['ot_date'])) . "\n";
                $message .= "• *Hours:* " . $data['hours'] . "\n";
                if (!empty($data['reason'])) {
                    $message .= "• *Reason:* " . $data['reason'] . "\n";
                }
            }
            
            $message .= "\n📊 *Status:* Pending Approval\n";
            $message .= "⏳ *Next Step:* Your manager will review this request\n\n";
            $message .= "📱 You will be notified once your request is approved or requires changes.\n\n";
            $message .= "🤖 *CIAH Attendance System*\n";
            $message .= "_Confirmation • " . date('Y-m-d H:i') . "_";
            
            // Send confirmation message
            $whatsappResult = sendWhatsAppMessage($user['whatsapp_number'], $message);
            
            if ($whatsappResult) {
                // Log confirmation notification
                $stmt = $this->db->prepare("INSERT INTO notifications (user_id, type, channel, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    'form_confirmation',
                    'whatsapp',
                    "{$typeLabel} Request Confirmation",
                    $message,
                    'sent'
                ]);
            }
            
        } catch (Exception $e) {
            error_log('[WhatsApp Confirmation] Failed to send: ' . $e->getMessage());
        }
    }
}

function getFormLinkManager(): FormLinkManager {
    static $manager = null;
    if ($manager === null) {
        $manager = new FormLinkManager();
    }
    return $manager;
}
