<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

$type = $_GET['type'] ?? 'daily';
$employee = trim((string)($_GET['employee'] ?? ''));
$company = trim((string)($_GET['company'] ?? ''));
$department = trim((string)($_GET['department'] ?? ''));
$nameMode = ($_GET['name_mode'] ?? 'calling') === 'official' ? 'official' : 'calling';
$db = getDB();

function filterPdfRows($rows, $employee) {
    if ($employee === '') return $rows;
    return array_values(array_filter($rows, function ($row) use ($employee) {
        return (string)$row['badge_id'] === $employee;
    }));
}

function filterPdfCompanyRows($rows, $company) {
    if ($company === '') return $rows;
    return array_values(array_filter($rows, function ($row) use ($company) {
        return (string)($row['company_name'] ?? '') === $company;
    }));
}

function filterPdfDepartmentRows($rows, $department) {
    if ($department === '') return $rows;
    return array_values(array_filter($rows, function ($row) use ($department) {
        return (string)($row['department_name'] ?? '') === $department;
    }));
}

function pdfReportName($row, $nameMode) {
    return $nameMode === 'official' ? ($row['official_name'] ?: $row['name']) : ($row['calling_name'] ?: $row['name']);
}

$css = '<style>
body{font-family:sans-serif;font-size:12px;color:#333;}
h1{font-size:18px;color:#1a237e;margin-bottom:5px;}
h2{font-size:14px;color:#555;margin-bottom:15px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th{background:#1a237e;color:white;padding:8px;text-align:left;font-size:11px;}
td{padding:6px 8px;border-bottom:1px solid #eee;font-size:11px;}
.late{background:#fff3f0;}
.badge{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;}
.badge-late{background:#ffebee;color:#c62828;}
.badge-ontime{background:#e8f5e9;color:#2e7d32;}
.stats{margin:10px 0;font-size:12px;}
.footer{margin-top:20px;font-size:10px;color:#999;text-align:center;}
</style>';

$html = '<html><head>' . $css . '</head><body>';
$html .= '<h1>' . htmlspecialchars(SYSTEM_NAME) . '</h1>';

function pdfRow($i, $badgeId, $name, $extra, $checkin, $checkout, $hours, $isLate, $isAbsent = false) {
    $cls = $isLate ? ' class="late"' : '';
    if ($isAbsent) $cls = ' class="absent"';
    $badge = $isAbsent ? '<span class="badge">Absent</span>' : ($isLate ? '<span class="badge badge-late">Late</span>' : '<span class="badge badge-ontime">On Time</span>');
    $row = "<tr{$cls}><td>{$i}</td><td>{$badgeId}</td><td>{$name}</td>";
    if ($extra !== null) $row .= "<td>{$extra}</td>";
    $row .= "<td>{$checkin}</td><td>{$checkout}</td><td><b>{$hours}</b></td><td>{$badge}</td></tr>";
    return $row;
}

switch ($type) {
    case 'daily':
        $date = $_GET['date'] ?? date('Y-m-d');
        $html .= "<h2>Daily Report - {$date}</h2>";
        $rows = filterPdfDepartmentRows(filterPdfCompanyRows(filterPdfRows(getDailySummary($db, $date, true), $employee), $company), $department);
        $html .= '<table><tr><th>#</th><th>ID</th><th>Name</th><th>Company</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>';
        $i = 1; $late = 0;
        foreach ($rows as $r) {
            $ci = $r['first_checkin'] ? date('H:i:s', strtotime($r['first_checkin'])) : '-';
            $co = $r['last_checkout'] ? date('H:i:s', strtotime($r['last_checkout'])) : '-';
            $hrs = calculateTotalHours($r['first_checkin'], $r['last_checkout']);
            $il = isLate($r['first_checkin']);
            if ($il) $late++;
            $isAbsent = (int)$r['attendance_count'] === 0;
            $html .= pdfRow($i++, $r['badge_id'], pdfReportName($r, $nameMode), $r['company_name'], $ci, $co, $hrs, $il, $isAbsent);
        }
        $html .= '</table>';
        $present = count(array_filter($rows, function ($r) { return (int)$r['attendance_count'] > 0; }));
        $onTime = $present - $late;
        $html .= '<div class="stats">Total Present: ' . $present . ' | Absent: ' . (count($rows) - $present) . ' | Late: ' . $late . ' | On Time: ' . $onTime . '</div>';
        break;

    case 'weekly':
        $date = $_GET['date'] ?? date('Y-m-d');
        $d = new DateTime($date);
        $d->modify('monday this week');
        $monday = $d->format('Y-m-d');
        $d->modify('sunday this week');
        $sunday = $d->format('Y-m-d');
        $html .= "<h2>Weekly Report - {$monday} to {$sunday}</h2>";
        $rows = filterPdfDepartmentRows(filterPdfCompanyRows(filterPdfRows(getWeeklyReport($db, $date), $employee), $company), $department);
        $week = [];
        $d2 = new DateTime($monday);
        for ($i = 0; $i < 7; $i++) { $week[] = $d2->format('Y-m-d'); $d2->modify('+1 day'); }
        $employees = [];
        foreach ($rows as $row) {
            $bid = $row['badge_id'];
            if (!isset($employees[$bid])) $employees[$bid] = ['badge_id' => $bid, 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'days' => []];
            $employees[$bid]['days'][$row['date']] = [
                'ci' => $row['first_checkin'] ? date('H:i', strtotime($row['first_checkin'])) : null,
                'co' => $row['last_checkout'] ? date('H:i', strtotime($row['last_checkout'])) : null,
            ];
        }
        $html .= '<table><tr><th>#</th><th>ID</th><th>Name</th><th>Company</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th><th>Total</th></tr>';
        $i = 1;
        foreach ($employees as $emp) {
            $total = 0; $cells = '';
            foreach ($week as $wd) {
                if (isset($emp['days'][$wd])) {
                    $d = $emp['days'][$wd];
                    $cells .= '<td>' . ($d['ci'] ? $d['ci'] . '-' . ($d['co'] ?? '') : '-') . '</td>';
                    if ($d['ci'] && $d['co']) { $diff = strtotime($d['co']) - strtotime($d['ci']); if ($diff > 0) $total += $diff; }
                } else { $cells .= '<td>-</td>'; }
            }
            $hrs = sprintf('%dh %02dm', floor($total / 3600), floor(($total % 3600) / 60));
            $html .= "<tr><td>{$i}</td><td>{$emp['badge_id']}</td><td>" . pdfReportName($emp, $nameMode) . "</td><td>{$emp['company_name']}</td>{$cells}<td><b>{$hrs}</b></td></tr>";
            $i++;
        }
        $html .= '</table>';
        break;

    case 'monthly':
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        $html .= "<h2>Monthly Report - {$year}-{$month}</h2>";
        $rows = filterPdfDepartmentRows(filterPdfCompanyRows(filterPdfRows(getMonthlyReport($db, $year, $month), $employee), $company), $department);
        $i = 1; $late = 0;
        foreach (array_chunk($rows, 100) as $chunkIndex => $chunk) {
            if ($chunkIndex > 0) $html .= '<div style="page-break-before: always"></div>';
            $html .= '<table><tr><th>#</th><th>ID</th><th>Name</th><th>Company</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>';
            foreach ($chunk as $r) {
                $ci = $r['first_checkin'] ? date('h:i:s A', strtotime($r['first_checkin'])) : '-';
                $co = $r['last_checkout'] ? date('h:i:s A', strtotime($r['last_checkout'])) : '-';
                $hrs = calculateTotalHours($r['first_checkin'], $r['last_checkout']);
                $il = isLate($r['first_checkin']);
                if ($il) $late++;
                $html .= pdfRow($i++, $r['badge_id'], pdfReportName($r, $nameMode), $r['company_name'] . '</td><td>' . $r['date'], $ci, $co, $hrs, $il);
            }
            $html .= '</table>';
        }
        $html .= '<div class="stats">Total Records: ' . count($rows) . ' | Late: ' . $late . '</div>';
        break;

    case 'custom':
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $html .= "<h2>Custom Report - {$from} to {$to}</h2>";
        $rows = filterPdfDepartmentRows(filterPdfCompanyRows(filterPdfRows(getCustomReport($db, $from, $to), $employee), $company), $department);
        $html .= '<table><tr><th>#</th><th>ID</th><th>Name</th><th>Company</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>';
        $i = 1; $late = 0;
        foreach ($rows as $r) {
            $ci = $r['first_checkin'] ? date('H:i:s', strtotime($r['first_checkin'])) : '-';
            $co = $r['last_checkout'] ? date('H:i:s', strtotime($r['last_checkout'])) : '-';
            $hrs = calculateTotalHours($r['first_checkin'], $r['last_checkout']);
            $il = isLate($r['first_checkin']);
            if ($il) $late++;
            $html .= pdfRow($i++, $r['badge_id'], pdfReportName($r, $nameMode), $r['company_name'] . '</td><td>' . $r['date'], $ci, $co, $hrs, $il);
        }
        $html .= '</table>';
        $html .= '<div class="stats">Total Records: ' . count($rows) . ' | Late: ' . $late . '</div>';
        break;
}

$html .= '<div class="footer">Generated on ' . date('Y-m-d H:i:s') . ' | ' . htmlspecialchars(COMPANY_NAME) . '</div>';
$html .= '</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("ciah_{$type}_report_" . date('Y-m-d') . ".pdf", ['Attachment' => true]);
