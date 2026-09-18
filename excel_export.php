<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ===================== PARAMETERS =====================
$type       = $_GET['type'] ?? 'daily';
if (!in_array($type, ['daily','weekly','monthly','custom'], true)) $type = 'daily';
$employee   = trim((string)($_GET['employee']   ?? ''));
$company    = trim((string)($_GET['company']    ?? ''));
$department = trim((string)($_GET['department'] ?? ''));
$nameMode   = ($_GET['name_mode'] ?? 'calling') === 'official' ? 'official' : 'calling';
$date       = $_GET['date']  ?? date('Y-m-d');
$year       = (int)($_GET['year']  ?? date('Y'));
$month      = (int)($_GET['month'] ?? date('m'));
$from       = $_GET['from']  ?? date('Y-m-01');
$to         = $_GET['to']    ?? date('Y-m-d');

$db      = getDB();
$sysName = defined('COMPANY_NAME') ? COMPANY_NAME : 'Attendance';

function xlCol($index) { return Coordinate::stringFromColumnIndex($index); }

// ===================== HELPERS =====================
function xlName($row, $nm) {
    return $nm === 'official' ? ($row['official_name'] ?: $row['name']) : ($row['calling_name'] ?: $row['name']);
}

function xlStatus($row) {
    $count = (int)($row['attendance_count'] ?? 0);
    $ci    = $row['first_checkin'] ?? '';
    if ($count === 0 && empty($ci) && empty($row['last_checkout'] ?? '')) return 'Absent';
    if (!empty($ci) && isLate($ci)) return 'Late';
    if (!empty($ci)) return 'On Time';
    return 'Absent';
}

function xlPeriod($type, $date, $year, $month, $from, $to) {
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
            return ['from' => $from, 'to' => $to, 'label' => $from . ' to ' . $to];
        default:
            return ['from' => $date, 'to' => $date, 'label' => $date];
    }
}

function xlRawRows($db, $type, $date, $year, $month, $from, $to) {
    switch ($type) {
        case 'weekly':  return getWeeklyReport($db, $date);
        case 'monthly': return getMonthlyReport($db, $year, $month);
        case 'custom':  return getCustomReport($db, $from, $to);
        default:        return getDailySummary($db, $date, true);
    }
}

function xlFilter($rows, $employee, $company, $department) {
    return array_values(array_filter($rows, function($r) use ($employee, $company, $department) {
        if ($employee   !== '' && (string)($r['badge_id']) !== $employee && (string)($r['employee_id'] ?? '') !== $employee) return false;
        if ($company    !== '' && ($r['company_name']    ?? '') !== $company)    return false;
        if ($department !== '' && ($r['department_name'] ?? '') !== $department) return false;
        return true;
    }));
}

function xlBuildStats($db, $rows) {
    $gs    = getGenderStats($db);
    $total = count($rows);
    $present = 0; $late = 0; $onTime = 0;
    $companies = []; $depts = [];

    foreach ($rows as $r) {
        $st    = xlStatus($r);
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

function xlBreakdownRows($map) {
    $out = [];
    foreach ($map as $name => $d) {
        $p = (int)($d['present'] ?? 0); $t = (int)($d['total'] ?? 0);
        $l = (int)($d['late']    ?? 0); $o = (int)($d['on_time'] ?? 0);
        $out[] = [
            'name'             => $name,
            'total'            => $t,
            'present'          => $p,
            'absent'           => max($t - $p, 0),
            'late'             => $l,
            'on_time'          => $o,
            'attendance_rate'  => $t > 0 ? round($p / $t * 100, 1) : 0,
            'punctuality_rate' => ($l+$o) > 0 ? round($o / ($l+$o) * 100, 1) : 0,
        ];
    }
    usort($out, fn($a,$b) => $b['total'] <=> $a['total']);
    return $out;
}

// Helper: style a row range
function xlStyleHeader($sheet, $range, $rgb) {
    $sheet->getStyle($range)->getFont()->setBold(true);
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
}

// ===================== RESOLVE DATA =====================
$period  = xlPeriod($type, $date, $year, $month, $from, $to);
$rawRows = xlRawRows($db, $type, $date, $year, $month, $from, $to);
$rows    = xlFilter($rawRows, $employee, $company, $department);
$stats   = xlBuildStats($db, $rows);
$cRows   = xlBreakdownRows($stats['companies']);
$dRows   = xlBreakdownRows($stats['departments']);
$gs      = $stats['gender_stats'];
$gTotal  = max((int)($gs['total_count'] ?? 0), 1);
$gMale   = (int)($gs['male_count']   ?? 0);
$gFemale = (int)($gs['female_count'] ?? 0);

// ===================== BUILD SPREADSHEET =====================
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator($sysName)
    ->setTitle($type . ' Attendance Report')
    ->setSubject('Attendance Report')
    ->setDescription('Generated by ' . $sysName);

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(ucfirst($type) . ' Report');
$r = 1;

// Banner
$sheet->setCellValue('A'.$r, $sysName . '  |  ' . strtoupper($type) . ' ATTENDANCE REPORT  |  ' . $period['label']);
$sheet->mergeCells('A'.$r.':M'.$r);
$sheet->getStyle('A'.$r)->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1e3a8a');
$sheet->getStyle('A'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension($r)->setRowHeight(26);
$r += 2;

// Meta
$sheet->setCellValue('A'.$r, 'Generated:');
$sheet->setCellValue('B'.$r, date('Y-m-d H:i:s'));
$sheet->setCellValue('E'.$r, 'Period:');
$sheet->setCellValue('F'.$r, $period['label']);
$sheet->getStyle('A'.$r.':F'.$r)->getFont()->setBold(true);
$r += 2;

// ---- OVERVIEW SECTION ----
$sheet->setCellValue('A'.$r, 'DASHBOARD OVERVIEW');
$sheet->mergeCells('A'.$r.':D'.$r);
xlStyleHeader($sheet, 'A'.$r.':D'.$r, 'dbeafe');
$sheet->getStyle('A'.$r)->getFont()->setSize(13);
$r++;

$sheet->setCellValue('A'.$r, 'METRIC');
$sheet->setCellValue('B'.$r, 'VALUE');
$sheet->setCellValue('C'.$r, 'PERCENTAGE');
xlStyleHeader($sheet, 'A'.$r.':C'.$r, 'bfdbfe');
$r++;

$overviewData = [
    ['Total Employees',  $stats['total_employees'], '100%'],
    [($type==='daily'?'Present Today':'Employees Present'), $stats['present'], $stats['attendance_rate'].'%'],
    ['Absent',           $stats['absent'],  $stats['total_employees']>0?round($stats['absent']/$stats['total_employees']*100,1).'%':'0%'],
    ['Late Instances',   $stats['late'],    ($stats['late']+$stats['on_time'])>0?round($stats['late']/($stats['late']+$stats['on_time'])*100,1).'%':'0%'],
    ['On Time Instances',$stats['on_time'], ($stats['late']+$stats['on_time'])>0?round($stats['on_time']/($stats['late']+$stats['on_time'])*100,1).'%':'0%'],
    ['Attendance Rate',  '',                $stats['attendance_rate'].'%'],
    ['Punctuality Rate', '',                $stats['punctuality_rate'].'%'],
];
foreach ($overviewData as $od) {
    $sheet->setCellValue('A'.$r, $od[0]);
    $sheet->setCellValue('B'.$r, $od[1]);
    $sheet->setCellValue('C'.$r, $od[2]);
    $r++;
}
$r++;

// ---- GENDER ----
$sheet->setCellValue('A'.$r, 'GENDER DISTRIBUTION');
$sheet->mergeCells('A'.$r.':C'.$r);
xlStyleHeader($sheet, 'A'.$r.':C'.$r, 'f3e8ff');
$sheet->getStyle('A'.$r)->getFont()->setSize(12);
$r++;
$sheet->setCellValue('A'.$r,'Gender'); $sheet->setCellValue('B'.$r,'Count'); $sheet->setCellValue('C'.$r,'Percentage');
xlStyleHeader($sheet, 'A'.$r.':C'.$r, 'e9d5ff');
$r++;
foreach ([['Male',$gMale,round($gMale/$gTotal*100,1).'%'],['Female',$gFemale,round($gFemale/$gTotal*100,1).'%'],['Total',$gMale+$gFemale,'100%']] as $g) {
    $sheet->setCellValue('A'.$r,$g[0]); $sheet->setCellValue('B'.$r,$g[1]); $sheet->setCellValue('C'.$r,$g[2]); $r++;
}
$r++;

// ---- COMPANY ----
if (count($cRows) > 1) {
    $sheet->setCellValue('A'.$r, 'COMPANY BREAKDOWN');
    $sheet->mergeCells('A'.$r.':H'.$r);
    xlStyleHeader($sheet, 'A'.$r.':H'.$r, 'bae6fd');
    $sheet->getStyle('A'.$r)->getFont()->setSize(12);
    $r++;
    $cH = ['Company','Total','Present','Absent','Late','On Time','Attend%','Punctual%'];
    $sheet->fromArray($cH, null, 'A'.$r);
    xlStyleHeader($sheet, 'A'.$r.':H'.$r, 'e0f2fe');
    $r++;
    foreach ($cRows as $c) {
        $sheet->fromArray([$c['name'],$c['total'],$c['present'],$c['absent'],$c['late'],$c['on_time'],$c['attendance_rate'].'%',$c['punctuality_rate'].'%'], null, 'A'.$r);
        $r++;
    }
    $r++;
}

// ---- DEPARTMENT ----
if (count($dRows) > 1) {
    $sheet->setCellValue('A'.$r, 'DEPARTMENT BREAKDOWN');
    $sheet->mergeCells('A'.$r.':H'.$r);
    xlStyleHeader($sheet, 'A'.$r.':H'.$r, 'a7f3d0');
    $sheet->getStyle('A'.$r)->getFont()->setSize(12);
    $r++;
    $dH = ['Department','Total','Present','Absent','Late','On Time','Attend%','Punctual%'];
    $sheet->fromArray($dH, null, 'A'.$r);
    xlStyleHeader($sheet, 'A'.$r.':H'.$r, 'dcfce7');
    $r++;
    foreach ($dRows as $d) {
        $sheet->fromArray([$d['name'],$d['total'],$d['present'],$d['absent'],$d['late'],$d['on_time'],$d['attendance_rate'].'%',$d['punctuality_rate'].'%'], null, 'A'.$r);
        $r++;
    }
    $r++;
}

// ---- DETAIL SECTION ----
$r++;
$sheet->setCellValue('A'.$r, 'DETAILED ATTENDANCE RECORDS — ' . $period['label']);
$sheet->mergeCells('A'.$r.':M'.$r);
xlStyleHeader($sheet, 'A'.$r.':M'.$r, 'fef3c7');
$sheet->getStyle('A'.$r)->getFont()->setSize(13);
$r += 2;

// Weekly grouping
if ($type === 'weekly') {
    $weekDates = [];
    $wd = new DateTime($period['from']);
    for ($i = 0; $i < 7; $i++) { $weekDates[] = $wd->format('Y-m-d'); $wd->modify('+1 day'); }
    $dayLabels = array_map(fn($d) => date('D d/m', strtotime($d)), $weekDates);

    $headers = array_merge(['#','EMP ID','Employee Name','Department','Company'], $dayLabels, ['Total Hours']);
    $sheet->fromArray($headers, null, 'A'.$r);
    xlStyleHeader($sheet, 'A'.$r.':'.xlCol(count($headers)).$r, 'f59e0b');
    $sheet->getStyle('A'.$r.':'.xlCol(count($headers)).$r)->getFont()->getColor()->setRGB('FFFFFF');
    $r++;

    $empMap = [];
    foreach ($rows as $row2) {
        $bid = $row2['badge_id'];
        if (!isset($empMap[$bid])) $empMap[$bid] = ['eid'=>$row2['employee_id']??$bid,'name'=>xlName($row2,$nameMode),'dept'=>$row2['department_name']??'','co'=>$row2['company_name']??'','days'=>[]];
        $empMap[$bid]['days'][$row2['date'] ?? ''] = ['ci'=>$row2['first_checkin'],'co'=>$row2['last_checkout']];
    }
    $n = 1;
    foreach ($empMap as $emp) {
        $totalSec = 0;
        $data = [$n++, $emp['eid'], $emp['name'], $emp['dept']?:'N/A', $emp['co']?:'N/A'];
        foreach ($weekDates as $wd2) {
            if (isset($emp['days'][$wd2]) && ($emp['days'][$wd2]['ci'] || $emp['days'][$wd2]['co'])) {
                $ci2 = $emp['days'][$wd2]['ci']; $co2 = $emp['days'][$wd2]['co'];
                $cell = $ci2 ? date('H:i', strtotime($ci2)) : '?';
                if ($co2) { $cell .= '-'.date('H:i',strtotime($co2)); $diff=strtotime($co2)-strtotime($ci2); if($diff>0)$totalSec+=$diff; }
                $data[] = $cell;
            } else {
                $data[] = 'Absent';
            }
        }
        $data[] = sprintf('%dh %02dm', floor($totalSec/3600), floor(($totalSec%3600)/60));
        $sheet->fromArray($data, null, 'A'.$r);
        $r++;
    }
} else {
    // Daily / Monthly / Custom
    if ($type === 'daily') {
        $headers = ['#','EMP ID','Enroll No','Employee Name','Gender','Company','Department','Check In','Check Out','Hours','Status'];
    } else {
        $headers = ['#','EMP ID','Enroll No','Employee Name','Company','Department','Date','Check In','Check Out','Hours','Status'];
    }
    $lastHdrCol = xlCol(count($headers));
    $sheet->fromArray($headers, null, 'A'.$r);
    xlStyleHeader($sheet, 'A'.$r.':'.$lastHdrCol.$r, 'f59e0b');
    $sheet->getStyle('A'.$r.':'.$lastHdrCol.$r)->getFont()->getColor()->setRGB('FFFFFF');
    $r++;

    // Force EMP ID (col B) and Enroll No (col C) to text so numbers don't right-align
    $sheet->getStyle('B1:B10000')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    $sheet->getStyle('C1:C10000')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

    $n = 1;
    $statusColIdx = count($headers); // last column
    foreach ($rows as $row2) {
        $ci    = $row2['first_checkin']  ?? '';
        $co    = $row2['last_checkout']  ?? '';
        $ci_f  = $ci ? date('H:i:s', strtotime($ci)) : 'Not Marked';
        $co_f  = $co ? date('H:i:s', strtotime($co)) : 'Not Marked';
        $hours = calculateTotalHours($ci, $co);
        $st    = xlStatus($row2);
        $g     = $row2['gender'] ?? '';

        // Fixed: always output exactly count($headers) columns, never skip or merge
        $data = [
            $n++,
            $row2['employee_id'] ?? '',          // EMP ID
            $row2['badge_id']    ?? '',          // Enroll No
            xlName($row2, $nameMode),
        ];
        if ($type === 'daily') {
            $data[] = $g === 'M' ? 'Male' : ($g === 'F' ? 'Female' : 'N/A'); // Gender
        }
        $data[] = $row2['company_name']    ?: 'N/A'; // Company
        $data[] = $row2['department_name'] ?: 'N/A'; // Department
        if ($type !== 'daily') {
            $data[] = $row2['date'] ?? '';           // Date (monthly/custom only)
        }
        $data[] = $ci_f;   // Check In
        $data[] = $co_f;   // Check Out
        $data[] = $hours;  // Hours
        $data[] = $st;     // Status

        $sheet->fromArray($data, null, 'A'.$r);
        // Force EMP ID and Enroll No as text to prevent numeric right-alignment
        $sheet->setCellValueExplicit('B'.$r, (string)($row2['employee_id'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C'.$r, (string)($row2['badge_id']    ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // Colour status cell
        $stCol = xlCol($statusColIdx);
        if ($st === 'Late') {
            $sheet->getStyle($stCol.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ffebee');
            $sheet->getStyle($stCol.$r)->getFont()->getColor()->setRGB('b91c1c');
            $sheet->getStyle($stCol.$r)->getFont()->setBold(true);
        } elseif ($st === 'Absent') {
            $sheet->getStyle($stCol.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('f3f4f6');
            $sheet->getStyle($stCol.$r)->getFont()->getColor()->setRGB('6b7280');
        } else {
            $sheet->getStyle($stCol.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('dcfce7');
            $sheet->getStyle($stCol.$r)->getFont()->getColor()->setRGB('15803d');
            $sheet->getStyle($stCol.$r)->getFont()->setBold(true);
        }
        $r++;
    }
}

// ===================== AUTO-SIZE ALL COLUMNS =====================
$maxCol = $sheet->getHighestColumn();
$maxColIdx = Coordinate::columnIndexFromString($maxCol);
for ($ci = 1; $ci <= $maxColIdx; $ci++) {
    $sheet->getColumnDimension(xlCol($ci))->setAutoSize(true);
}

// ===================== OUTPUT =====================
$writer   = new Xlsx($spreadsheet);
$filename = $sysName . '_' . $type . '_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
