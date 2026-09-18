<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

$action = $_GET['action'] ?? '';
$badgeId = $_GET['badge_id'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'daily';

if ($action === 'download') {
    $file = $_GET['file'] ?? '';
    $filepath = sys_get_temp_dir() . '/' . basename($file);
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    unlink($filepath);
    exit;
}

header('Content-Type: application/json');

if ($action !== 'generate' || empty($badgeId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$db = getDB();

function generateEmployeePDF($db, $badgeId, $date, $type) {
    $stmt = $db->prepare("SELECT * FROM users WHERE badge_id = :badge_id LIMIT 1");
    $stmt->execute([':badge_id' => $badgeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        return ['success' => false, 'message' => 'Employee not found'];
    }
    
    $employeeName = $employee['calling_name'] ?: $employee['name'];
    
    if ($type === 'daily') {
        $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
        $stmt = $db->prepare("SELECT 
            MIN(CASE WHEN state IN (0, 2, 4) THEN check_time END) as first_checkin,
            MAX(CASE WHEN state IN (1, 3, 5) THEN check_time END) as last_checkout
            FROM attendance 
            WHERE user_id = :user_id 
            AND check_time >= :date 
            AND check_time < :next_date");
        $stmt->execute([':user_id' => $employee['id'], ':date' => $date, ':next_date' => $nextDate]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $checkIn = $record['first_checkin'] ? date('h:i:s A', strtotime($record['first_checkin'])) : 'Not Marked In';
        $checkOut = $record['last_checkout'] ? date('h:i:s A', strtotime($record['last_checkout'])) : 'Not Marked Out';
        $hours = calculateTotalHours($record['first_checkin'], $record['last_checkout']);
        $isLate = isLate($record['first_checkin']);
        
        if (!$record['first_checkin'] && !$record['last_checkout']) {
            $status = 'Absent';
        } else if (!$record['first_checkin']) {
            $status = 'Not Marked In';
        } else if (!$record['last_checkout']) {
            $status = 'Not Marked Out';
        } else if ($isLate) {
            $status = 'Late';
        } else {
            $status = 'On Time';
        }
        
        $html = buildDailyPDF($employee, $employeeName, $date, $checkIn, $checkOut, $hours, $status, $isLate, $record);
    } else if ($type === 'monthly') {
        $parts = explode('-', $date);
        $year = $parts[0];
        $month = $parts[1];
        
        $stmt = $db->prepare("SELECT DATE(check_time) as date,
            MIN(CASE WHEN state IN (0, 2, 4) THEN check_time END) as first_checkin,
            MAX(CASE WHEN state IN (1, 3, 5) THEN check_time END) as last_checkout
            FROM attendance 
            WHERE user_id = :user_id 
            AND strftime('%Y-%m', check_time) = :ym
            GROUP BY DATE(check_time)
            ORDER BY date");
        $stmt->execute([':user_id' => $employee['id'], ':ym' => sprintf('%04d-%02d', $year, $month)]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = buildMonthlyPDF($employee, $employeeName, $year, $month, $records);
    } else if ($type === 'weekly') {
        $d = new DateTime($date);
        $d->modify('monday this week');
        $monday = $d->format('Y-m-d');
        $d->modify('sunday this week');
        $sunday = $d->format('Y-m-d');
        $week = [];
        $d2 = new DateTime($monday);
        for ($i = 0; $i < 7; $i++) { $week[] = $d2->format('Y-m-d'); $d2->modify('+1 day'); }
        
        $weekPlaceholders = implode(',', array_fill(0, 7, '?'));
        $params = array_merge([$employee['id']], $week);
        $stmt = $db->prepare("SELECT DATE(check_time) as date,
            MIN(CASE WHEN state IN (0, 2, 4) THEN check_time END) as first_checkin,
            MAX(CASE WHEN state IN (1, 3, 5) THEN check_time END) as last_checkout
            FROM attendance 
            WHERE user_id = ? 
            AND DATE(check_time) IN ($weekPlaceholders)
            GROUP BY DATE(check_time)
            ORDER BY date");
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = buildWeeklyPDF($employee, $employeeName, $monday, $sunday, $week, $records);
    } else if ($type === 'custom') {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        
        $stmt = $db->prepare("SELECT DATE(check_time) as date,
            MIN(CASE WHEN state IN (0, 2, 4) THEN check_time END) as first_checkin,
            MAX(CASE WHEN state IN (1, 3, 5) THEN check_time END) as last_checkout
            FROM attendance 
            WHERE user_id = :user_id 
            AND DATE(check_time) BETWEEN :from AND :to
            GROUP BY DATE(check_time)
            ORDER BY date");
        $stmt->execute([':user_id' => $employee['id'], ':from' => $from, ':to' => $to]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $html = buildCustomPDF($employee, $employeeName, $from, $to, $records);
    } else {
        return ['success' => false, 'message' => 'Invalid report type'];
    }
    
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = "attendance_{$badgeId}_{$type}_{$date}.pdf";
    $filepath = sys_get_temp_dir() . '/' . $filename;
    file_put_contents($filepath, $dompdf->output());
    
    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'download_url' => 'api/employee_pdf.php?action=download&file=' . urlencode($filename)
    ];
}

function buildDailyPDF($employee, $employeeName, $date, $checkIn, $checkOut, $hours, $status, $isLate, $record) {
    if ($status === 'Absent' || $status === 'Not Marked In' || $status === 'Not Marked Out') {
        $statusColor = '#dc2626';
        $statusBg = '#fee2e2';
    } else if ($status === 'Late') {
        $statusColor = '#ea580c';
        $statusBg = '#fed7aa';
    } else {
        $statusColor = '#16a34a';
        $statusBg = '#dcfce7';
    }
    
    $css = '<style>
body{font-family:Arial,sans-serif;font-size:13px;color:#333;margin:20px;}
.header{text-align:center;margin-bottom:30px;border-bottom:3px solid #1a237e;padding-bottom:15px;}
.header h1{font-size:24px;color:#1a237e;margin:0 0 5px 0;}
.header h2{font-size:16px;color:#666;margin:0;}
.employee-info{background:#f5f5f5;padding:15px;border-radius:8px;margin:20px 0;}
.employee-info h3{color:#1a237e;margin:0 0 10px 0;font-size:18px;}
.info-row{display:flex;justify-content:space-between;margin:8px 0;border-bottom:1px solid #ddd;padding-bottom:5px;}
.info-label{font-weight:bold;color:#555;}
.info-value{color:#333;}
.attendance-card{border:2px solid #e0e0e0;border-radius:10px;padding:20px;margin:20px 0;}
.time-row{display:flex;justify-content:space-between;margin:15px 0;padding:12px;background:#fafafa;border-radius:6px;}
.time-label{font-weight:bold;color:#555;font-size:14px;}
.time-value{font-size:16px;font-weight:bold;color:#1a237e;}
.time-value-missing{font-size:16px;font-weight:bold;color:#dc2626;}
.status-box{text-align:center;padding:15px;border-radius:8px;margin:20px 0;}
.footer{text-align:center;margin-top:30px;padding-top:15px;border-top:2px solid #e0e0e0;color:#999;font-size:11px;}
.late-warning{background:#fff3f0;border-left:4px solid #dc2626;padding:10px;margin:10px 0;color:#dc2626;font-size:12px;}
</style>';
    
    $html = '<html><head>' . $css . '</head><body>';
    $html .= '<div class="header">';
    $html .= '<h1>' . htmlspecialchars(SYSTEM_NAME) . '</h1>';
    $html .= '<h2>Daily Attendance Report</h2>';
    $html .= '<p style="margin:5px 0;color:#888;">Date: ' . htmlspecialchars($date) . '</p>';
    $html .= '</div>';
    
    $html .= '<div class="employee-info">';
    $html .= '<h3>Employee Details</h3>';
    $html .= '<div class="info-row"><span class="info-label">Name:</span><span class="info-value">' . htmlspecialchars($employeeName) . '</span></div>';
    $html .= '<div class="info-row"><span class="info-label">Employee ID:</span><span class="info-value">' . htmlspecialchars($employee['employee_id'] ?: $employee['badge_id']) . '</span></div>';
    $html .= '<div class="info-row"><span class="info-label">Enroll Number:</span><span class="info-value">' . htmlspecialchars($employee['badge_id']) . '</span></div>';
    $html .= '<div class="info-row"><span class="info-label">Company:</span><span class="info-value">' . htmlspecialchars($employee['company_name'] ?: '-') . '</span></div>';
    $html .= '<div class="info-row"><span class="info-label">Department:</span><span class="info-value">' . htmlspecialchars($employee['department_name'] ?: '-') . '</span></div>';
    $html .= '</div>';
    
    $html .= '<div class="attendance-card">';
    $html .= '<div class="time-row"><span class="time-label">Check In:</span><span class="' . ($checkIn === 'Not Marked In' ? 'time-value-missing' : 'time-value') . '">' . htmlspecialchars($checkIn) . '</span></div>';
    $html .= '<div class="time-row"><span class="time-label">Check Out:</span><span class="' . ($checkOut === 'Not Marked Out' ? 'time-value-missing' : 'time-value') . '">' . htmlspecialchars($checkOut) . '</span></div>';
    $html .= '<div class="time-row"><span class="time-label">Total Hours:</span><span class="time-value">' . htmlspecialchars($hours) . '</span></div>';
    $html .= '</div>';
    
    $html .= '<div class="status-box" style="background:' . $statusBg . ';color:' . $statusColor . ';border:2px solid ' . $statusColor . ';">';
    $html .= '<h3 style="margin:0;font-size:20px;">Status: ' . htmlspecialchars($status) . '</h3>';
    $html .= '</div>';
    
    if ($checkIn === 'Not Marked In') {
        $html .= '<div class="late-warning"><strong>Missing Check-In:</strong> Employee did not mark attendance for this day.</div>';
    } else if ($isLate) {
        $html .= '<div class="late-warning"><strong>Late Arrival:</strong> Employee checked in after 8:45 AM.</div>';
    }
    if ($checkOut === 'Not Marked Out') {
        $html .= '<div class="late-warning"><strong>Missing Check-Out:</strong> Employee did not mark out for this day.</div>';
    }
    
    $html .= '<div class="footer">';
    $html .= 'Generated on ' . date('Y-m-d h:i A') . '<br>';
    $html .= htmlspecialchars(COMPANY_NAME) . ' | ' . htmlspecialchars(SYSTEM_NAME);
    $html .= '</div>';
    $html .= '</body></html>';
    
    return $html;
}

function buildMonthlyPDF($employee, $employeeName, $year, $month, $records) {
    $css = getTablePDFCss();
    $html = '<html><head>' . $css . '</head><body>';
    $html .= getEmployeeHeader($employee, $employeeName);
    $html .= '<h2>Monthly Attendance Report - ' . $year . '-' . sprintf('%02d', $month) . '</h2>';
    $html .= buildAttendanceTable($records, 'monthly');
    $html .= buildSummary($records);
    $html .= getFooter();
    $html .= '</body></html>';
    return $html;
}

function buildWeeklyPDF($employee, $employeeName, $monday, $sunday, $week, $records) {
    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $byDate = [];
    foreach ($records as $r) { $byDate[$r['date']] = $r; }
    
    $css = getTablePDFCss();
    $html = '<html><head>' . $css . '</head><body>';
    $html .= getEmployeeHeader($employee, $employeeName);
    $html .= '<h2>Weekly Attendance Report - ' . $monday . ' to ' . $sunday . '</h2>';
    
    $html .= '<table><tr><th>#</th><th>Date</th><th>Day</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>';
    $i = 1;
    foreach ($week as $wd) {
        $r = $byDate[$wd] ?? null;
        $ci = $r && $r['first_checkin'] ? date('h:i A', strtotime($r['first_checkin'])) : '-';
        $co = $r && $r['last_checkout'] ? date('h:i A', strtotime($r['last_checkout'])) : '-';
        $hrs = $r ? calculateTotalHours($r['first_checkin'], $r['last_checkout']) : '-';
        $dayOfWeek = (new DateTime($wd))->format('N');
        $dayName = $dayNames[$dayOfWeek - 1];
        
        if (!$r || (!$r['first_checkin'] && !$r['last_checkout'])) {
            $status = 'No attendance';
            $rowClass = 'absent-row';
        } else if (!$r['first_checkin']) {
            $status = 'Not Marked In';
            $rowClass = 'absent-row';
        } else if (!$r['last_checkout']) {
            $status = 'Not Marked Out';
            $rowClass = 'late-row';
        } else if (isLate($r['first_checkin'])) {
            $status = 'Late';
            $rowClass = 'late-row';
        } else {
            $status = 'On Time';
            $rowClass = 'ontime-row';
        }
        
        $html .= '<tr class="' . $rowClass . '">';
        $html .= '<td>' . $i++ . '</td>';
        $html .= '<td>' . htmlspecialchars($wd) . '</td>';
        $html .= '<td>' . $dayName . '</td>';
        $html .= '<td>' . ($ci === '-' ? '<span class="missing-time">-</span>' : htmlspecialchars($ci)) . '</td>';
        $html .= '<td>' . ($co === '-' ? '<span class="missing-time">-</span>' : htmlspecialchars($co)) . '</td>';
        $html .= '<td><strong>' . htmlspecialchars($hrs) . '</strong></td>';
        $html .= '<td class="' . ($rowClass === 'absent-row' ? 'status-absent' : ($rowClass === 'late-row' ? 'status-late' : 'status-ontime')) . '">' . $status . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    
    $html .= buildSummary($records);
    $html .= getFooter();
    $html .= '</body></html>';
    return $html;
}

function buildCustomPDF($employee, $employeeName, $from, $to, $records) {
    $css = getTablePDFCss();
    $html = '<html><head>' . $css . '</head><body>';
    $html .= getEmployeeHeader($employee, $employeeName);
    $html .= '<h2>Custom Attendance Report - ' . $from . ' to ' . $to . '</h2>';
    $html .= buildAttendanceTable($records, 'custom');
    $html .= buildSummary($records);
    $html .= getFooter();
    $html .= '</body></html>';
    return $html;
}

function getTablePDFCss() {
    return '<style>
body{font-family:Arial,sans-serif;font-size:11px;color:#333;margin:15px;}
h1{font-size:20px;color:#1a237e;margin:0 0 3px 0;}
h2{font-size:14px;color:#666;margin:0 0 10px 0;}
.header{text-align:center;margin-bottom:20px;border-bottom:3px solid #1a237e;padding-bottom:10px;}
.employee-info{background:#f5f5f5;padding:10px;border-radius:6px;margin:10px 0;font-size:11px;}
.employee-info strong{color:#1a237e;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th{background:#1a237e;color:white;padding:8px 4px;text-align:left;font-size:10px;}
td{padding:6px 4px;border-bottom:1px solid #eee;font-size:10px;}
.late-row{background:#fff3f0;}
.ontime-row{background:#f0fdf4;}
.absent-row{background:#fee2e2;}
.status-late{color:#dc2626;font-weight:bold;}
.status-ontime{color:#16a34a;font-weight:bold;}
.status-absent{color:#991b1b;font-weight:bold;}
.missing-time{color:#dc2626;font-weight:bold;}
.summary{margin:15px 0;padding:10px;background:#f5f5f5;border-radius:6px;font-size:11px;}
.footer{text-align:center;margin-top:15px;padding-top:10px;border-top:2px solid #e0e0e0;color:#999;font-size:9px;}
</style>';
}

function getEmployeeHeader($employee, $employeeName) {
    $html = '<div class="header">';
    $html .= '<h1>' . htmlspecialchars(SYSTEM_NAME) . '</h1>';
    $html .= '</div>';
    $html .= '<div class="employee-info">';
    $html .= '<strong>Employee:</strong> ' . htmlspecialchars($employeeName) . ' | ';
    $html .= '<strong>ID:</strong> ' . htmlspecialchars($employee['employee_id'] ?: $employee['badge_id']) . ' | ';
    $html .= '<strong>Enroll:</strong> ' . htmlspecialchars($employee['badge_id']) . ' | ';
    $html .= '<strong>Company:</strong> ' . htmlspecialchars($employee['company_name'] ?: '-') . ' | ';
    $html .= '<strong>Department:</strong> ' . htmlspecialchars($employee['department_name'] ?: '-');
    $html .= '</div>';
    return $html;
}

function buildAttendanceTable($records, $mode) {
    $html = '<table>';
    $html .= '<tr><th>#</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>';
    
    foreach ($records as $i => $r) {
        $ci = $r['first_checkin'] ? date('h:i A', strtotime($r['first_checkin'])) : '<span class="missing-time">Not Marked In</span>';
        $co = $r['last_checkout'] ? date('h:i A', strtotime($r['last_checkout'])) : '<span class="missing-time">Not Marked Out</span>';
        $hrs = calculateTotalHours($r['first_checkin'], $r['last_checkout']);
        $isLate = isLate($r['first_checkin']);
        
        if (!$r['first_checkin'] && !$r['last_checkout']) {
            $status = 'Absent';
            $statusClass = 'status-absent';
            $rowClass = 'absent-row';
        } else if (!$r['first_checkin']) {
            $status = 'Not Marked In';
            $statusClass = 'status-absent';
            $rowClass = 'absent-row';
        } else if (!$r['last_checkout']) {
            $status = 'Not Marked Out';
            $statusClass = 'status-late';
            $rowClass = 'late-row';
        } else if ($isLate) {
            $status = 'Late';
            $statusClass = 'status-late';
            $rowClass = 'late-row';
        } else {
            $status = 'On Time';
            $statusClass = 'status-ontime';
            $rowClass = 'ontime-row';
        }
        
        $html .= '<tr class="' . $rowClass . '">';
        $html .= '<td>' . ($i + 1) . '</td>';
        $html .= '<td>' . htmlspecialchars($r['date']) . '</td>';
        $html .= '<td>' . $ci . '</td>';
        $html .= '<td>' . $co . '</td>';
        $html .= '<td><strong>' . htmlspecialchars($hrs) . '</strong></td>';
        $html .= '<td class="' . $statusClass . '">' . $status . '</td>';
        $html .= '</tr>';
    }
    
    if (empty($records)) {
        $html .= '<tr><td colspan="6" style="text-align:center;padding:20px;color:#999;">No attendance records for this period</td></tr>';
    }
    
    $html .= '</table>';
    return $html;
}

function buildSummary($records) {
    $totalDays = 0;
    $lateDays = 0;
    $absentDays = 0;
    $onTimeDays = 0;
    
    foreach ($records as $r) {
        $totalDays++;
        $isLate = isLate($r['first_checkin']);
        
        if (!$r['first_checkin'] && !$r['last_checkout']) {
            $absentDays++;
        } else if (!$r['first_checkin']) {
            $absentDays++;
        } else if (!$r['last_checkout'] || $isLate) {
            $lateDays++;
        } else {
            $onTimeDays++;
        }
    }
    
    $html = '<div class="summary">';
    $html .= '<strong>Summary:</strong> ';
    $html .= 'Total Days: ' . $totalDays . ' | ';
    $html .= 'On Time: ' . $onTimeDays . ' | ';
    $html .= 'Late: ' . $lateDays . ' | ';
    $html .= 'Absent: ' . $absentDays;
    $html .= '</div>';
    return $html;
}

function getFooter() {
    $html = '<div class="footer">';
    $html .= 'Generated on ' . date('Y-m-d h:i A') . '<br>';
    $html .= htmlspecialchars(COMPANY_NAME) . ' | ' . htmlspecialchars(SYSTEM_NAME);
    $html .= '</div>';
    return $html;
}

$result = generateEmployeePDF($db, $badgeId, $date, $type);
echo json_encode($result);
