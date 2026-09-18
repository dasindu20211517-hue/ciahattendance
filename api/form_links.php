<?php
/**
 * Form Link Generator API
 * 
 * GET /api/form_links.php?action=generate&type=leave&user_id=123
 * GET /api/form_links.php?action=get_url&token=xxx
 * GET /api/form_links.php?action=list&type=leave
 * POST /api/form_links.php?action=deactivate
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/form_manager.php';

try {
    $action = $_GET['action'] ?? 'generate';
    $manager = getFormLinkManager();
    $db = getDB();
    
    if ($action === 'list') {
        $formType = $_GET['type'] ?? null;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        if (!$formType || !in_array($formType, ['leave', 'overtime'])) throw new Exception('Valid form type required');
        $query = "SELECT fl.id, fl.token, fl.form_type, fl.user_id, fl.created_at, fl.expires_at, fl.is_active, COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name, (SELECT COUNT(*) FROM form_submissions WHERE form_link_id = fl.id) as submission_count FROM form_links fl LEFT JOIN users u ON fl.user_id = u.id WHERE fl.form_type = ?";
        $params = [$formType];
        if ($userId) { $query .= " AND fl.user_id = ?"; $params[] = $userId; }
        $query .= " ORDER BY fl.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $links = array_map(function($link) { $link['url'] = FormLinkManager::getFormURL($link['token']); return $link; }, $links);
        http_response_code(200);
        echo json_encode(['success' => true, 'links' => $links]);
        exit;
    }
    
    if ($action === 'deactivate') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $token = $data['token'] ?? null;
        if (!$token) throw new Exception('Token is required');
        if ($manager->deactivateFormLink($token)) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Form link deactivated successfully']);
            exit;
        } else {
            throw new Exception('Failed to deactivate form link');
        }
    }

    if ($action === 'clear_type') {
        $formType = $_GET['type'] ?? $_POST['type'] ?? null;
        if (!$formType || !in_array($formType, ['leave', 'overtime'])) {
            throw new Exception('Valid form type required (leave or overtime)');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("DELETE FROM form_submissions WHERE form_type = ?");
            $stmt->execute([$formType]);

            $stmt = $db->prepare("DELETE FROM form_links WHERE form_type = ?");
            $stmt->execute([$formType]);

            $db->commit();
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => ucfirst($formType) . ' form links cleared successfully'
            ]);
            exit;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    if ($action === 'generate') {
        // Generate new form link
        $formType = $_GET['type'] ?? $_POST['type'] ?? null;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : null);
        $expireDays = isset($_GET['expire_days']) ? (int)$_GET['expire_days'] : 30;
        
        if (!$formType || !in_array($formType, ['leave', 'overtime'])) {
            throw new Exception('Valid form type required (leave or overtime)');
        }
        
        $token = $manager->generateFormLink($formType, $userId, null, $expireDays);
        $url = FormLinkManager::getFormURL($token);
        
        $whatsappSent = false;
        
        if ($userId) {
            $emp = $db->query("SELECT whatsapp_number, COALESCE(NULLIF(calling_name,''), name) as name FROM users WHERE id=" . (int)$userId)->fetch(PDO::FETCH_ASSOC);
            
            // Send via WhatsApp (primary method)
            if ($emp && !empty($emp['whatsapp_number'])) {
                require_once __DIR__ . '/../config_whatsapp.php';
                require_once __DIR__ . '/../whatsapp_web_sender.php';
                
                $typeLabel = $formType === 'leave' ? 'Leave' : 'Overtime';
                $typeEmoji = $formType === 'leave' ? '🏖️' : '⏰';
                
                $whatsappMessage = "$typeEmoji *{$typeLabel} Request Form*\n";
                $whatsappMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $whatsappMessage .= "Hi {$emp['name']}! 👋\n\n";
                $whatsappMessage .= "You have been invited to submit a *{$typeLabel} Request*.\n\n";
                $whatsappMessage .= "📋 *Please fill out the form using this link:*\n";
                $whatsappMessage .= "{$url}\n\n";
                $whatsappMessage .= "⏰ *Important:*\n";
                $whatsappMessage .= "• This link will expire in *{$expireDays} days*\n";
                $whatsappMessage .= "• Please submit your request as soon as possible\n";
                $whatsappMessage .= "• You will receive confirmation once submitted\n\n";
                $whatsappMessage .= "📱 *CIAH Attendance System*\n";
                $whatsappMessage .= "_Automated Form Link • " . date('Y-m-d H:i') . "_";
                
                try {
                    $whatsappResult = sendWhatsAppMessage($emp['whatsapp_number'], $whatsappMessage);
                    if ($whatsappResult) {
                        $whatsappSent = true;
                        
                        // Log WhatsApp notification
                        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, channel, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $userId,
                            'form_link',
                            'whatsapp',
                            "{$typeLabel} Form Link",
                            $whatsappMessage,
                            'sent'
                        ]);
                    }
                } catch (Exception $e) {
                    error_log('[WhatsApp Form Link] Failed to send: ' . $e->getMessage());
                }
            } else {
                error_log('[WhatsApp Form Link] No WhatsApp number found for user ID: ' . $userId);
            }
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'url' => $url,
            'form_type' => $formType,
            'expires_in_days' => $expireDays,
            'whatsapp_sent' => $whatsappSent,
            'message' => $whatsappSent ? 'Form link generated and sent via WhatsApp' : 'Form link generated (WhatsApp number not available)'
        ]);
        
    } elseif ($action === 'get_url') {
        // Get URL for existing token
        $token = $_GET['token'] ?? null;
        if (!$token) {
            throw new Exception('Token is required');
        }
        
        $formLink = $manager->getFormLink($token);
        if (!$formLink) {
            throw new Exception('Token is invalid or expired');
        }
        
        $url = FormLinkManager::getFormURL($token);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'url' => $url,
            'form_type' => $formLink['form_type'],
            'created_at' => $formLink['created_at'],
            'expires_at' => $formLink['expires_at'],
            'is_active' => $formLink['is_active']
        ]);
        
    } elseif ($action === 'stats') {
        // Get stats for a token
        $token = $_GET['token'] ?? null;
        if (!$token) {
            throw new Exception('Token is required');
        }
        
        $stats = $manager->getFormStats($token);
        if (!$stats) {
            throw new Exception('Token not found');
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        
    } else {
        throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
