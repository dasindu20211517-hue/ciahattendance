<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ===================== PARAMETERS =====================
$type      = $_GET['type'] ?? 'daily';
if (!in_array($type, ['daily','weekly','monthly','custom'], true)) $type = 'daily';
$format    = ($_GET['format'] ?? 'pdf') === 'csv' ? 'csv' : 'pdf';
$employee  = trim((string)($_GET['employee'] ?? ''));
$company   = trim((string)($_GET['company'] ?? ''));
$department= trim((string)($_GET['department'] ?? ''));
$nameMode  = ($_GET['name_mode'] ?? 'calling') === 'official' ? 'official' : 'calling';
$date      = $_GET['date'] ?? date('Y-m-d');
$year      = (int)($_GET['year'] ?? date('Y'));
$month     = (int)($_GET['month'] ?? date('m'));
$from      = $_GET['from'] ?? '';
$to        = $_GET['to'] ?? '';

$db        = getDB();
$sysName   = defined('COMPANY_NAME') ? COMPANY_NAME : 'Attendance System';

// ===================== HELPERS =====================
function xHtml($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function xName($row, $nameMode) {
    return $nameMode === 'official'
        ? ($row['official_name'] ?: $row['name'])
        : ($row['calling_name']  ?: $row['name']);
}

function xStatus($row) {
    $count = (int)($row['attendance_count'] ?? 0);
    $ci    = $row['first_checkin'] ?? '';
    if ($count === 0 && empty($ci) && empty($row['last_checkout'] ?? '')) return 'Absent';
    if (!empty($ci) && isLate($ci)) return 'Late';
    if (!empty($ci)) return 'On Time';
    return 'Absent';
}

function xPeriod($type, $date, $year, $month, $from, $to) {
    switch ($type) {
        case 'weekly':
            $d = new DateTime($date);
            $d->modify('monday this week');
            $mon = $d->format('Y-m-d');
            $d->modify('+6 days');
            return ['from' => $mon, 'to' => $d->format('Y-m-d'), 'label' => $mon . ' to ' . $d->format('Y-m-d')];
        case 'monthly':
            $f = sprintf('%04d-%02d-01', $year, $month);
            $last = (new DateTime($f))->modify('last day of this month')->format('Y-m-d');
            return ['from' => $f, 'to' => $last, 'label' => date('F Y', strtotime($f))];
        case 'custom':
            return ['from' => $from, 'to' => $to, 'label' => ($from . ' to ' . $to)];
        default:
            return ['from' => $date, 'to' => $date, 'label' => $date];
    }
}

function xRawRows($db, $type, $date, $year, $month, $from, $to) {
    switch ($type) {
        case 'weekly':  return getWeeklyReport($db, $date);
        case 'monthly': return getMonthlyReport($db, $year, $month);
        case 'custom':  return getCustomReport($db, $from, $to);
        default:        return getDailySummary($db, $date, true);
    }
}

function xFilter($rows, $employee, $company, $department) {
    return array_values(array_filter($rows, function($r) use ($employee, $company, $department) {
        if ($employee   !== '' && (string)($r['badge_id']) !== $employee && (string)($r['employee_id'] ?? '') !== $employee) return false;
        if ($company    !== '' && ($r['company_name']    ?? '') !== $company)    return false;
        if ($department !== '' && ($r['department_name'] ?? '') !== $department) return false;
        return true;
    }));
}

function xBuildStats($db, $rows) {
    $gs = getGenderStats($db);
    $total     = count($rows);
    $present   = 0; $late = 0; $onTime = 0;
    $companies = []; $depts = [];

    foreach ($rows as $r) {
        $st  = xStatus($r);
        $cname = $r['company_name']    ?: 'No Company';
        $dname = $r['department_name'] ?: 'No Department';

        if (!isset($companies[$cname])) $companies[$cname] = ['total'=>0,'present'=>0,'late'=>0,'on_time'=>0];
        if (!isset($depts[$dname]))     $depts[$dname]     = ['total'=>0,'present'=>0,'late'=>0,'on_time'=>0];

        $companies[$cname]['total']++;
        $depts[$dname]['total']++;

        if ($st !== 'Absent') {
            $present++;
            if ($st === 'Late') { $late++; $companies[$cname]['late']++; $depts[$dname]['late']++; }
            else                { $onTime++; $companies[$cname]['on_time']++; $depts[$dname]['on_time']++; }
            $companies[$cname]['present']++;
            $depts[$dname]['present']++;
        }
    }

    return [
        'total_employees' => $total,
        'present'  => $present,
        'absent'   => $total - $present,
        'late'     => $late,
        'on_time'  => $onTime,
        'attendance_rate'  => $total  > 0 ? round($present / $total * 100, 1) : 0,
        'punctuality_rate' => ($late + $onTime) > 0 ? round($onTime / ($late + $onTime) * 100, 1) : 0,
        'gender_stats' => $gs,
        'companies'    => $companies,
        'departments'  => $depts,
    ];
}

function xBreakdownRows($map) {
    $out = [];
    foreach ($map as $name => $d) {
        $p = (int)($d['present'] ?? 0);
        $t = (int)($d['total']   ?? 0);
        $l = (int)($d['late']    ?? 0);
        $o = (int)($d['on_time'] ?? 0);
        $out[] = [
            'name'              => $name,
            'total'             => $t,
            'present'           => $p,
            'absent'            => max($t - $p, 0),
            'late'              => $l,
            'on_time'           => $o,
            'attendance_rate'   => $t > 0 ? round($p / $t * 100, 1) : 0,
            'punctuality_rate'  => ($l+$o) > 0 ? round($o / ($l+$o) * 100, 1) : 0,
        ];
    }
    usort($out, fn($a,$b) => $b['total'] <=> $a['total']);
    return $out;
}

// ===================== RESOLVE DATA =====================
$period    = xPeriod($type, $date, $year, $month, $from, $to);
$rawRows   = xRawRows($db, $type, $date, $year, $month, $from, $to);
$rows      = xFilter($rawRows, $employee, $company, $department);
$stats     = xBuildStats($db, $rows);
$cRows     = xBreakdownRows($stats['companies']);
$dRows     = xBreakdownRows($stats['departments']);
$gs        = $stats['gender_stats'];
$gTotal    = max((int)($gs['total_count'] ?? 0), 1);
$gMale     = (int)($gs['male_count']   ?? 0);
$gFemale   = (int)($gs['female_count'] ?? 0);

// ===================== CSV OUTPUT =====================
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $sysName . '_' . $type . '_' . date('Ymd') . '.csv"');
    header('Cache-Control: max-age=0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    fputcsv($out, [$sysName . ' — ' . strtoupper($type) . ' ATTENDANCE REPORT']);
    fputcsv($out, ['Period:', $period['label'], '', 'Generated:', date('Y-m-d H:i:s')]);
    fputcsv($out, []);

    fputcsv($out, ['=== OVERVIEW ===']);
    fputcsv($out, ['METRIC','VALUE','%']);
    fputcsv($out, ['Total Employees',  $stats['total_employees'], '100%']);
    fputcsv($out, ['Present',          $stats['present'],         $stats['attendance_rate'].'%']);
    fputcsv($out, ['Absent',           $stats['absent'],          ($stats['total_employees']>0?round($stats['absent']/$stats['total_employees']*100,1):0).'%']);
    fputcsv($out, ['Late',             $stats['late'],            $stats['punctuality_rate'].'%']);
    fputcsv($out, ['On Time',          $stats['on_time'],         ($stats['late']+$stats['on_time'])>0?round($stats['on_time']/($stats['late']+$stats['on_time'])*100,1).'%':'0%']);
    fputcsv($out, ['Attendance Rate',  '',                        $stats['attendance_rate'].'%']);
    fputcsv($out, ['Punctuality Rate', '',                        $stats['punctuality_rate'].'%']);
    fputcsv($out, []);

    fputcsv($out, ['=== GENDER ===']);
    fputcsv($out, ['GENDER','COUNT','%']);
    fputcsv($out, ['Male',   $gMale,   round($gMale/$gTotal*100,1).'%']);
    fputcsv($out, ['Female', $gFemale, round($gFemale/$gTotal*100,1).'%']);
    fputcsv($out, ['Total',  $gMale+$gFemale, '100%']);
    fputcsv($out, []);

    if (count($cRows) > 1) {
        fputcsv($out, ['=== COMPANY BREAKDOWN ===']);
        fputcsv($out, ['COMPANY','TOTAL','PRESENT','ABSENT','LATE','ON TIME','ATTEND%','PUNCTUAL%']);
        foreach ($cRows as $c) fputcsv($out, [$c['name'],$c['total'],$c['present'],$c['absent'],$c['late'],$c['on_time'],$c['attendance_rate'].'%',$c['punctuality_rate'].'%']);
        fputcsv($out, []);
    }

    if (count($dRows) > 1) {
        fputcsv($out, ['=== DEPARTMENT BREAKDOWN ===']);
        fputcsv($out, ['DEPARTMENT','TOTAL','PRESENT','ABSENT','LATE','ON TIME','ATTEND%','PUNCTUAL%']);
        foreach ($dRows as $d) fputcsv($out, [$d['name'],$d['total'],$d['present'],$d['absent'],$d['late'],$d['on_time'],$d['attendance_rate'].'%',$d['punctuality_rate'].'%']);
        fputcsv($out, []);
    }

    fputcsv($out, ['=== DETAILED RECORDS ===']);
    if ($type === 'daily') {
        fputcsv($out, ['NO','EMP ID','BADGE ID','EMPLOYEE NAME','GENDER','COMPANY','DEPARTMENT','CHECK IN','CHECK OUT','HOURS','STATUS']);
        $i = 1;
        foreach ($rows as $r) {
            fputcsv($out, [
                $i++,
                $r['employee_id'] ?? $r['badge_id'], $r['badge_id'],
                xName($r, $nameMode),
                $r['gender'] === 'M' ? 'Male' : ($r['gender'] === 'F' ? 'Female' : 'N/A'),
                $r['company_name'] ?: 'N/A', $r['department_name'] ?: 'N/A',
                $r['first_checkin']  ? date('H:i:s', strtotime($r['first_checkin']))  : 'Not Marked',
                $r['last_checkout']  ? date('H:i:s', strtotime($r['last_checkout']))  : 'Not Marked',
                calculateTotalHours($r['first_checkin'], $r['last_checkout']),
                xStatus($r),
            ]);
        }
    } else {
        fputcsv($out, ['NO','EMP ID','BADGE ID','EMPLOYEE NAME','COMPANY','DEPARTMENT','DATE','CHECK IN','CHECK OUT','HOURS','STATUS']);
        $i = 1;
        foreach ($rows as $r) {
            fputcsv($out, [
                $i++,
                $r['employee_id'] ?? $r['badge_id'], $r['badge_id'],
                xName($r, $nameMode),
                $r['company_name'] ?: 'N/A', $r['department_name'] ?: 'N/A',
                $r['date'] ?? '',
                $r['first_checkin']  ? date('H:i:s', strtotime($r['first_checkin']))  : 'Not Marked',
                $r['last_checkout']  ? date('H:i:s', strtotime($r['last_checkout']))  : 'Not Marked',
                calculateTotalHours($r['first_checkin'], $r['last_checkout']),
                xStatus($r),
            ]);
        }
    }

    fclose($out);
    exit;
}

// ===================== PDF OUTPUT =====================
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

$css = '
@page { margin: 10mm 10mm; size: A4 landscape; }
* { box-sizing: border-box; }
body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 7.5px; color: #1f2937; margin:0; padding:0; line-height:1.3; }

.titlebar { background: #1e3a8a; color: white; padding: 7px 12px; border-radius: 4px; margin-bottom: 8px; }
.titlebar h1 { margin: 0 0 1px 0; font-size: 14px; font-weight: 700; }
.titlebar .sub { font-size: 7px; color: #bfdbfe; }

.dashboard-wrap { page-break-inside: avoid; }

.section-title { font-size: 8.5px; font-weight: 700; color: #1e40af; border-left: 3px solid #2563eb; padding-left: 5px; margin: 6px 0 4px 0; }

.stats-grid { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.stats-grid td { padding: 2px; width: 12.5%; }
.stat-card { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 4px; text-align: center; background: #f8fafc; }
.stat-value { display: block; font-size: 13px; font-weight: 800; color: #1e40af; }
.stat-label { display: block; font-size: 6px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 1px; }
.stat-pct { display: block; font-size: 6px; color: #9ca3af; }

.side-by-side { width: 100%; border-collapse: collapse; margin-top: 5px; }
.side-by-side td { vertical-align: top; padding: 0 3px; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th { background: #1e40af; color: #ffffff; padding: 4px 3px; font-size: 6.5px; font-weight: 600; text-align: left; }
.data-table td { padding: 3px; font-size: 7px; border-bottom: 1px solid #f3f4f6; border-right: 1px solid #f3f4f6; }
.data-table td:last-child { border-right: none; }
.data-table tr:nth-child(even) td { background: #f9fafb; }
.row-late   td { background: #fef2f2 !important; border-left: 2px solid #ef4444; }
.row-absent td { background: #f9fafb !important; border-left: 2px solid #9ca3af; }
.row-ontime td { background: #f0fdf4 !important; border-left: 2px solid #22c55e; }

.st-late   { color: #b91c1c; font-weight: 700; }
.st-absent { color: #6b7280; }
.st-ontime { color: #15803d; font-weight: 700; }

.footer { margin-top: 8px; border-top: 1px solid #d1d5db; padding-top: 4px; font-size: 6.5px; color: #9ca3af; text-align: center; }
.page-break { page-break-before: always; }
';

$periodLabel = $period['label'];
$typeLabel   = ucfirst($type);

$html = '<html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>';

// Header
$html .= '<div class="titlebar"><h1>' . xHtml($sysName) . '</h1>';
$html .= '<div class="sub">' . xHtml($typeLabel) . ' Attendance Report &nbsp;|&nbsp; Period: ' . xHtml($periodLabel) . ' &nbsp;|&nbsp; Generated: ' . date('Y-m-d H:i:s') . '</div></div>';

// Stats cards
$blocks = [
    ['Total Employees',  $stats['total_employees'], ''],
    ['Present',          $stats['present'],         $stats['attendance_rate'].'%'],
    ['Absent',           $stats['absent'],           $stats['total_employees']>0?round($stats['absent']/$stats['total_employees']*100,1).'%':'0%'],
    ['Late',             $stats['late'],             ($stats['late']+$stats['on_time'])>0?round($stats['late']/($stats['late']+$stats['on_time'])*100,1).'%':'0%'],
    ['On Time',          $stats['on_time'],          ($stats['late']+$stats['on_time'])>0?round($stats['on_time']/($stats['late']+$stats['on_time'])*100,1).'%':'0%'],
    ['Attendance Rate',  $stats['attendance_rate'].'%', ''],
    ['Punctuality Rate', $stats['punctuality_rate'].'%', ''],
    ['Gender M / F',     $gMale.' / '.$gFemale,    ''],
];

$html .= '<div class="dashboard-wrap">';
$html .= '<div class="section-title">Dashboard Overview</div>';
$html .= '<table class="stats-grid"><tr>';
foreach ($blocks as $b) {
    $html .= '<td><div class="stat-card"><span class="stat-value">' . xHtml($b[1]) . '</span><span class="stat-label">' . xHtml($b[0]) . '</span>';
    if ($b[2] !== '') $html .= '<span class="stat-pct">' . xHtml($b[2]) . '</span>';
    $html .= '</div></td>';
}
$html .= '</tr></table>';

// Breakdown tables side by side
$colCount = ($gTotal > 0 ? 1 : 0) + (count($cRows)>1?1:0) + (count($dRows)>1?1:0);
if ($colCount < 1) $colCount = 1;
$colW = round(100/$colCount, 0);

$html .= '<table class="side-by-side"><tr>';

// Gender
$html .= '<td style="width:' . $colW . '%">';
$html .= '<div class="section-title">Gender Distribution</div>';
$html .= '<table class="data-table"><tr><th>Gender</th><th>Count</th><th>%</th></tr>';
$html .= '<tr><td>Male</td><td>'.$gMale.'</td><td>'.round($gMale/$gTotal*100,1).'%</td></tr>';
$html .= '<tr><td>Female</td><td>'.$gFemale.'</td><td>'.round($gFemale/$gTotal*100,1).'%</td></tr>';
$html .= '<tr><td><strong>Total</strong></td><td><strong>'.($gMale+$gFemale).'</strong></td><td><strong>100%</strong></td></tr>';
$html .= '</table></td>';

// Companies
if (count($cRows) > 1) {
    $html .= '<td style="width:' . $colW . '%">';
    $html .= '<div class="section-title">Company Breakdown</div>';
    $html .= '<table class="data-table"><tr><th>Company</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>Att%</th></tr>';
    foreach ($cRows as $c) {
        $html .= '<tr><td>'.xHtml(substr($c['name'],0,18)).'</td><td>'.$c['total'].'</td><td>'.$c['present'].'</td><td>'.$c['absent'].'</td><td>'.$c['late'].'</td><td>'.$c['attendance_rate'].'%</td></tr>';
    }
    $html .= '</table></td>';
}

// Departments
if (count($dRows) > 1) {
    $html .= '<td style="width:' . $colW . '%">';
    $html .= '<div class="section-title">Department Breakdown</div>';
    $html .= '<table class="data-table"><tr><th>Department</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>Att%</th></tr>';
    foreach ($dRows as $d) {
        $html .= '<tr><td>'.xHtml(substr($d['name'],0,16)).'</td><td>'.$d['total'].'</td><td>'.$d['present'].'</td><td>'.$d['absent'].'</td><td>'.$d['late'].'</td><td>'.$d['attendance_rate'].'%</td></tr>';
    }
    $html .= '</table></td>';
}

$html .= '</tr></table>';
$html .= '</div>'; // close dashboard-wrap

// ===================== DETAILED RECORDS (new page) =====================
$html .= '<div class="page-break"></div>';
$html .= '<div class="section-title">Detailed Attendance Records — ' . xHtml($periodLabel) . '</div>';

if ($type === 'weekly') {
    $weekDays   = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $weekDates  = [];
    $wd = new DateTime($period['from']);
    for ($i = 0; $i < 7; $i++) { $weekDates[] = $wd->format('Y-m-d'); $wd->modify('+1 day'); }

    // Group by employee
    $empMap = [];
    foreach ($rows as $r) {
        $bid = $r['badge_id'];
        if (!isset($empMap[$bid])) {
            $empMap[$bid] = ['badge_id'=>$bid,'employee_id'=>$r['employee_id']??$bid,'name'=>xName($r,$nameMode),'dept'=>$r['department_name']??'','co'=>$r['company_name']??'','days'=>[]];
        }
        $empMap[$bid]['days'][$r['date'] ?? ''] = ['ci'=>$r['first_checkin'],'co'=>$r['last_checkout']];
    }

    $html .= '<table class="data-table"><tr><th>#</th><th>EMP ID</th><th>Employee Name</th><th>Department</th>';
    foreach ($weekDays as $dl) $html .= '<th>'.$dl.'</th>';
    $html .= '<th>Total Hrs</th></tr>';

    $n = 1;
    foreach ($empMap as $emp) {
        $totalSec = 0;
        $html .= '<tr><td>'.$n++.'</td><td>'.xHtml($emp['employee_id']).'</td><td>'.xHtml($emp['name']).'</td><td>'.xHtml($emp['dept']?:'N/A').'</td>';
        foreach ($weekDates as $wd2) {
            if (isset($emp['days'][$wd2]) && ($emp['days'][$wd2]['ci'] || $emp['days'][$wd2]['co'])) {
                $ci = $emp['days'][$wd2]['ci']; $co = $emp['days'][$wd2]['co'];
                $cell = $ci ? date('H:i', strtotime($ci)) : '?';
                if ($co) { $cell .= '-'.date('H:i',strtotime($co)); $diff=strtotime($co)-strtotime($ci); if($diff>0) $totalSec+=$diff; }
                $html .= '<td>'.$cell.'</td>';
            } else {
                $html .= '<td class="st-absent">—</td>';
            }
        }
        $html .= '<td>'.sprintf('%dh%02dm',floor($totalSec/3600),floor(($totalSec%3600)/60)).'</td></tr>';
    }
    $html .= '</table>';
} else {
    if ($type === 'daily') {
        $html .= '<table class="data-table"><tr><th>#</th><th>EMP ID</th><th>Badge</th><th>Employee Name</th><th>Gender</th><th>Company</th><th>Department</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>';
    } else {
        $html .= '<table class="data-table"><tr><th>#</th><th>EMP ID</th><th>Badge</th><th>Employee Name</th><th>Company</th><th>Department</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Status</th></tr>';
    }
    $n = 1;
    foreach ($rows as $r) {
        $ci    = $r['first_checkin'] ?? '';
        $co    = $r['last_checkout'] ?? '';
        $ci_f  = $ci ? date('H:i:s', strtotime($ci)) : 'Not Marked';
        $co_f  = $co ? date('H:i:s', strtotime($co)) : 'Not Marked';
        $hours = calculateTotalHours($ci, $co);
        $st    = xStatus($r);
        $stCls = $st === 'Late' ? 'st-late' : ($st === 'Absent' ? 'st-absent' : 'st-ontime');
        $rowCls= $st === 'Late' ? 'row-late' : ($st === 'Absent' ? 'row-absent' : 'row-ontime');

        $html .= '<tr class="'.$rowCls.'">';
        $html .= '<td>'.$n++.'</td>';
        $html .= '<td>'.xHtml($r['employee_id'] ?? $r['badge_id']).'</td>';
        $html .= '<td>'.xHtml($r['badge_id']).'</td>';
        $html .= '<td>'.xHtml(xName($r,$nameMode)).'</td>';
        if ($type === 'daily') {
            $g = $r['gender'] ?? '';
            $html .= '<td>'.($g==='M'?'M':($g==='F'?'F':'—')).'</td>';
        }
        $html .= '<td>'.xHtml($r['company_name']    ?: '—').'</td>';
        $html .= '<td>'.xHtml($r['department_name'] ?: '—').'</td>';
        if ($type !== 'daily') $html .= '<td>'.xHtml($r['date'] ?? '').'</td>';
        $html .= '<td>'.$ci_f.'</td>';
        $html .= '<td>'.$co_f.'</td>';
        $html .= '<td><strong>'.$hours.'</strong></td>';
        $html .= '<td class="'.$stCls.'">'.$st.'</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
}

$html .= '<div class="footer">'.xHtml($sysName).' | '.xHtml($typeLabel).' Attendance Report | Period: '.xHtml($periodLabel).' | Generated: '.date('Y-m-d H:i:s').'</div>';
$html .= '</body></html>';

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream($sysName . '_' . $type . '_' . date('Ymd') . '.pdf', ['Attachment' => true]);
