<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
requireLoginApi([1, 2]);
$action = $_GET['action'] ?? $_POST['action'] ?? 'daily';
if ($action !== 'users' && !isAdminUser()) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Attendance reports are restricted to administrators.']); exit; }

try {
    $db = getDB();
    $employee = trim((string)($_GET['employee'] ?? ''));
    $company = trim((string)($_GET['company'] ?? ''));
        $department = trim((string)($_GET['department'] ?? ''));
    $search = strtolower(trim((string)($_GET['search'] ?? '')));
    $filterRows = function ($rows) use ($employee) {
        if ($employee === '') return $rows;
        return array_values(array_filter($rows, function ($row) use ($employee) {
            return (string)($row['employee_id'] ?? $row['badge_id']) === $employee || (string)$row['badge_id'] === $employee;
        }));
    };
    $filterSearch = function ($rows) use ($search) {
        if ($search === '') return $rows;
        return array_values(array_filter($rows, function ($row) use ($search) {
            $text = strtolower(implode(' ', [$row['badge_id'] ?? '', $row['name'] ?? '', $row['department_name'] ?? '']));
            return strpos($text, $search) !== false;
        }));
    };
    $filterCompany = function ($rows) use ($company) {
        if ($company === '') return $rows;
        return array_values(array_filter($rows, function ($row) use ($company) {
            return (string)($row['company_name'] ?? '') === $company;
        }));
    };
        $filterDepartment = function ($rows) use ($department) {
            if ($department === '') return $rows;
            return array_values(array_filter($rows, function ($row) use ($department) {
                return (string)($row['department_name'] ?? '') === $department;
            }));
        };
    switch ($action) {
        case 'daily':
            $date = $_GET['date'] ?? date('Y-m-d');
            $dateHoliday = isHoliday($db, $date);
            $holidayName = null;
            if ($dateHoliday) { $hs = $db->prepare("SELECT name FROM public_holidays WHERE holiday_date = ?"); $hs->execute([$date]); $holidayName = $hs->fetchColumn(); }
            
            // Get total employee count
            $totalEmployees = (int)$db->query("SELECT COUNT(*) FROM users WHERE employee_list_member = 1")->fetchColumn();
            
            $rows = $filterDepartment($filterCompany($filterSearch($filterRows(getDailySummary($db, $date, true)))));
            $result = [];
            $presentCount = 0;
            $lateCount = 0;
            $onTimeCount = 0;
            
            foreach ($rows as $row) {
                $firstIn = $row['first_checkin'];
                $lastOut = $row['last_checkout'];
                $isAbsent = (int)$row['attendance_count'] === 0;
                $worked = !$isAbsent && ($firstIn || $lastOut);
                
                // Count statistics
                if (!$isAbsent && $worked) {
                    $presentCount++;
                    $userId = $db->query("SELECT id FROM users WHERE badge_id = " . $db->quote($row['badge_id']))->fetchColumn();
                    $isLateValue = $userId && $firstIn ? isLateForUserOnDate($db, $userId, $date, $firstIn) : false;
                    if ($isLateValue) {
                        $lateCount++;
                    } else {
                        $onTimeCount++;
                    }
                }

                if ($dateHoliday) {
                    if ($worked) {
                        $userId = $db->query("SELECT id FROM users WHERE badge_id = " . $db->quote($row['badge_id']))->fetchColumn();
                        $isLateValue = $userId && $firstIn ? isLateForUserOnDate($db, $userId, $date, $firstIn) : false;
                        $result[] = [
                            'badge_id' => $row['badge_id'], 'employee_id' => normalizeEmployeeIdentifier($row['employee_id'] ?? $row['badge_id']), 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'gender' => $row['gender'] ?? '', 'whatsapp_number' => $row['whatsapp_number'] ?? '',
                            'check_in' => formatAttendanceTime($firstIn, true), 'check_out' => formatAttendanceTime($lastOut, true),
                            'total_hours' => calculateTotalHours($firstIn, $lastOut),
                            'status' => 'Holiday', 'is_holiday' => true, 'holiday_name' => $holidayName,
                            'is_late' => $isLateValue, 'is_absent' => false, 'worked_on_holiday' => true,
                        ];
                    } else {
                        $result[] = [
                            'badge_id' => $row['badge_id'], 'employee_id' => normalizeEmployeeIdentifier($row['employee_id'] ?? $row['badge_id']), 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'gender' => $row['gender'] ?? '', 'whatsapp_number' => $row['whatsapp_number'] ?? '',
                            'check_in' => null, 'check_out' => null, 'total_hours' => '0h 00m',
                            'status' => 'Holiday', 'is_holiday' => true, 'holiday_name' => $holidayName,
                            'is_late' => false, 'is_absent' => false, 'worked_on_holiday' => false,
                        ];
                    }
                } else {
                    $userId = $db->query("SELECT id FROM users WHERE badge_id = " . $db->quote($row['badge_id']))->fetchColumn();
                    $isLateValue = $userId && $firstIn ? isLateForUserOnDate($db, $userId, $date, $firstIn) : false;
                    $missingCheckout = $firstIn && !$lastOut;
                    $result[] = [
                        'badge_id' => $row['badge_id'], 'employee_id' => normalizeEmployeeIdentifier($row['employee_id'] ?? $row['badge_id']), 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'gender' => $row['gender'] ?? '', 'whatsapp_number' => $row['whatsapp_number'] ?? '',
                        'check_in' => formatAttendanceTime($firstIn, true), 'check_out' => formatAttendanceTime($lastOut, true),
                        'total_hours' => calculateTotalHours($firstIn, $lastOut),
                        'status' => $isAbsent ? 'Absent' : getAttendanceStatusWithTime($firstIn, $lastOut, $date),
                        'is_late' => $isLateValue, 'is_absent' => $isAbsent, 'worked_on_holiday' => false, 'missing_checkout' => $missingCheckout,
                    ];
                }
            }
            echo json_encode([
                'success' => true, 
                'date' => $date, 
                'is_holiday' => $dateHoliday, 
                'holiday_name' => $holidayName, 
                'data' => $result,
                'stats' => [
                    'total_employees' => $totalEmployees,
                    'present' => $presentCount,
                    'late' => $lateCount,
                    'on_time' => $onTimeCount,
                    'absent' => $totalEmployees - $presentCount
                ]
            ]);
            break;
        case 'weekly':
            $date = $_GET['date'] ?? date('Y-m-d');
            $rows = $filterDepartment($filterCompany($filterSearch($filterRows(getWeeklyReport($db, $date)))));
            $d = new DateTime($date);
            $d->modify('monday this week');
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $d->format('Y-m-d');
                $d->modify('+1 day');
            }
            $weekHolidays = [];
            foreach ($week as $wd) {
                $hs = $db->prepare("SELECT name FROM public_holidays WHERE holiday_date = ?");
                $hs->execute([$wd]);
                $hn = $hs->fetchColumn();
                $weekHolidays[$wd] = $hn;
            }
            $employees = [];
            foreach ($rows as $row) {
                $bid = $row['badge_id'];
                if (!isset($employees[$bid])) {
                    $employees[$bid] = ['badge_id' => $bid, 'employee_id' => $row['employee_id'] ?? $bid, 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'whatsapp_number' => $row['whatsapp_number'] ?? '', 'days' => []];
                }
                $dayHoliday = !empty($weekHolidays[$row['date']]);
                $hasAttendance = !empty($row['first_checkin']) || !empty($row['last_checkout']);
                $employees[$bid]['days'][$row['date']] = [
                    'check_in' => $row['first_checkin'] ? formatAttendanceTime($row['first_checkin'], false) : null,
                    'check_out' => $row['last_checkout'] ? formatAttendanceTime($row['last_checkout'], false) : null,
                    'status' => $dayHoliday ? 'Holiday' : getAttendanceStatusWithTime($row['first_checkin'], $row['last_checkout'], $row['date']),
                    'is_holiday' => $dayHoliday,
                    'holiday_name' => $dayHoliday ? $weekHolidays[$row['date']] : null,
                    'worked_on_holiday' => $dayHoliday && $hasAttendance,
                ];
            }
            $result = [];
            foreach ($employees as $emp) {
                $totalHours = 0;
                $days = [];
                foreach ($week as $wd) {
                    if (isset($emp['days'][$wd])) {
                        $d = $emp['days'][$wd];
                        $days[] = ['date' => $wd, 'check_in' => $d['check_in'], 'check_out' => $d['check_out'], 'status' => $d['status'], 'is_holiday' => $d['is_holiday'], 'holiday_name' => $d['holiday_name'], 'worked_on_holiday' => $d['worked_on_holiday']];
                        if ($d['check_in'] && $d['check_out']) {
                            $start = strtotime($wd . ' ' . $d['check_in']);
                            $end = strtotime($wd . ' ' . $d['check_out']);
                            if ($end < $start) $end += 86400;
                            $diff = $end - $start;
                            if ($diff > 0) $totalHours += $diff;
                        }
                    } else {
                        $isDayHoliday = !empty($weekHolidays[$wd]);
                        $days[] = ['date' => $wd, 'check_in' => null, 'check_out' => null, 'status' => $isDayHoliday ? 'Holiday' : 'No attendance', 'is_holiday' => $isDayHoliday, 'holiday_name' => $isDayHoliday ? $weekHolidays[$wd] : null, 'worked_on_holiday' => false];
                    }
                }
                $result[] = ['badge_id' => $emp['badge_id'], 'employee_id' => $emp['employee_id'] ?? $emp['badge_id'], 'name' => $emp['name'], 'official_name' => $emp['official_name'], 'calling_name' => $emp['calling_name'], 'company_name' => $emp['company_name'], 'department_name' => $emp['department_name'], 'days' => $days, 'total_hours' => sprintf('%dh %02dm', floor($totalHours / 3600), floor(($totalHours % 3600) / 60))];
            }
            echo json_encode(['success' => true, 'week' => $week, 'holidays' => $weekHolidays, 'data' => $result]);
            break;
        case 'monthly':
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            $rows = $filterDepartment($filterCompany($filterSearch($filterRows(getMonthlySummary($db, $year, $month)))));
            echo json_encode(['success' => true, 'year' => $year, 'month' => $month, 'data' => $rows]);
            break;
        case 'monthly_detail':
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            $rows = $filterDepartment($filterCompany($filterSearch($filterRows(getMonthlyReport($db, $year, $month)))));
            $workingDays = count(array_unique(array_column($rows, 'date')));
            $monthHolidays = [];
            $hs = $db->prepare("SELECT holiday_date, name FROM public_holidays WHERE year = ? AND strftime('%m', holiday_date) = ?");
            $hs->execute([$year, sprintf('%02d', $month)]);
            while ($hr = $hs->fetch(PDO::FETCH_ASSOC)) { $monthHolidays[$hr['holiday_date']] = $hr['name']; }
            $result = [];
            foreach ($rows as $row) {
                $firstIn = $row['first_checkin'];
                $lastOut = $row['last_checkout'];
                $dayHoliday = !empty($monthHolidays[$row['date']]);
                $isAbsent = !$firstIn && !$lastOut;
                $worked = ($firstIn || $lastOut);
                
                if ($dayHoliday && $worked) {
                    $userId = $db->query("SELECT id FROM users WHERE badge_id = " . $db->quote($row['badge_id']))->fetchColumn();
                    $isLateValue = $userId && $firstIn ? isLateForUserOnDate($db, $userId, $row['date'], $firstIn) : false;
                    $missingCheckout = $firstIn && !$lastOut;
                    $result[] = [
                        'badge_id' => $row['badge_id'], 'employee_id' => $row['employee_id'] ?? $row['badge_id'], 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'whatsapp_number' => $row['whatsapp_number'] ?? '', 'date' => $row['date'],
                        'check_in' => formatAttendanceTime($firstIn, true), 'check_out' => formatAttendanceTime($lastOut, true),
                        'total_hours' => calculateTotalHours($firstIn, $lastOut),
                        'status' => 'Holiday', 'is_holiday' => true, 'holiday_name' => $monthHolidays[$row['date']], 'worked_on_holiday' => true,
                        'is_late' => $isLateValue, 'missing_checkout' => $missingCheckout,
                    ];
                } elseif ($dayHoliday) {
                    $result[] = [
                        'badge_id' => $row['badge_id'], 'employee_id' => $row['employee_id'] ?? $row['badge_id'], 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'whatsapp_number' => $row['whatsapp_number'] ?? '', 'date' => $row['date'],
                        'check_in' => null, 'check_out' => null, 'total_hours' => '0h 00m',
                        'status' => 'Holiday', 'is_holiday' => true, 'holiday_name' => $monthHolidays[$row['date']], 'worked_on_holiday' => false,
                        'is_late' => false, 'missing_checkout' => false,
                    ];
                } else {
                    $userId = $db->query("SELECT id FROM users WHERE badge_id = " . $db->quote($row['badge_id']))->fetchColumn();
                    $isLateValue = $userId && $firstIn ? isLateForUserOnDate($db, $userId, $row['date'], $firstIn) : false;
                    $missingCheckout = $firstIn && !$lastOut;
                    $result[] = [
                        'badge_id' => $row['badge_id'], 'employee_id' => $row['employee_id'] ?? $row['badge_id'], 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'whatsapp_number' => $row['whatsapp_number'] ?? '', 'date' => $row['date'],
                        'check_in' => formatAttendanceTime($firstIn, true),
                        'check_out' => formatAttendanceTime($lastOut, true),
                        'total_hours' => calculateTotalHours($firstIn, $lastOut),
                        'status' => getAttendanceStatusWithTime($firstIn, $lastOut, $row['date']),
                        'is_late' => $isLateValue, 'worked_on_holiday' => false, 'missing_checkout' => $missingCheckout,
                    ];
                }
            }
            echo json_encode(['success' => true, 'year' => $year, 'month' => $month, 'working_days' => $workingDays, 'holidays' => $monthHolidays, 'data' => $result]);
            break;
        case 'custom':
            $from = $_GET['from'] ?? date('Y-m-01');
            $to = $_GET['to'] ?? date('Y-m-d');
            $rows = $filterDepartment($filterCompany($filterSearch($filterRows(getCustomReport($db, $from, $to)))));
            $customHolidays = [];
            $hs = $db->prepare("SELECT holiday_date, name FROM public_holidays WHERE holiday_date BETWEEN ? AND ?");
            $hs->execute([$from, $to]);
            while ($hr = $hs->fetch(PDO::FETCH_ASSOC)) { $customHolidays[$hr['holiday_date']] = $hr['name']; }
            $result = [];
            foreach ($rows as $row) {
                $firstIn = $row['first_checkin'];
                $lastOut = $row['last_checkout'];
                $dayHoliday = !empty($customHolidays[$row['date']]);
                $isAbsent = !$firstIn && !$lastOut;
                $worked = ($firstIn || $lastOut);
                
                if ($dayHoliday && $worked) {
                    $userId = $db->query("SELECT id FROM users WHERE badge_id = " . $db->quote($row['badge_id']))->fetchColumn();
                    $isLateValue = $userId && $firstIn ? isLateForUserOnDate($db, $userId, $row['date'], $firstIn) : false;
                    $missingCheckout = $firstIn && !$lastOut;
                    $result[] = [
                        'badge_id' => $row['badge_id'], 'employee_id' => $row['employee_id'] ?? $row['badge_id'], 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'whatsapp_number' => $row['whatsapp_number'] ?? '', 'date' => $row['date'],
                        'check_in' => formatAttendanceTime($firstIn, true), 'check_out' => formatAttendanceTime($lastOut, true),
                        'total_hours' => calculateTotalHours($firstIn, $lastOut),
                        'status' => 'Holiday', 'is_holiday' => true, 'holiday_name' => $customHolidays[$row['date']], 'worked_on_holiday' => true,
                        'is_late' => $isLateValue, 'missing_checkout' => $missingCheckout,
                    ];
                } elseif ($dayHoliday) {
                    $result[] = [
                        'badge_id' => $row['badge_id'], 'employee_id' => $row['employee_id'] ?? $row['badge_id'], 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'whatsapp_number' => $row['whatsapp_number'] ?? '', 'date' => $row['date'],
                        'check_in' => null, 'check_out' => null, 'total_hours' => '0h 00m',
                        'status' => 'Holiday', 'is_holiday' => true, 'holiday_name' => $customHolidays[$row['date']], 'worked_on_holiday' => false,
                        'is_late' => false, 'missing_checkout' => false,
                    ];
                } else {
                    $userId = $db->query("SELECT id FROM users WHERE badge_id = " . $db->quote($row['badge_id']))->fetchColumn();
                    $isLateValue = $userId && $firstIn ? isLateForUserOnDate($db, $userId, $row['date'], $firstIn) : false;
                    $missingCheckout = $firstIn && !$lastOut;
                    $result[] = [
                        'badge_id' => $row['badge_id'], 'employee_id' => $row['employee_id'] ?? $row['badge_id'], 'name' => $row['name'], 'official_name' => $row['official_name'], 'calling_name' => $row['calling_name'], 'company_name' => $row['company_name'], 'department_name' => $row['department_name'], 'whatsapp_number' => $row['whatsapp_number'] ?? '', 'date' => $row['date'],
                        'check_in' => formatAttendanceTime($firstIn, true),
                        'check_out' => formatAttendanceTime($lastOut, true),
                        'total_hours' => calculateTotalHours($firstIn, $lastOut),
                        'status' => getAttendanceStatusWithTime($firstIn, $lastOut, $row['date']),
                        'is_late' => $isLateValue, 'worked_on_holiday' => false, 'missing_checkout' => $missingCheckout,
                    ];
                }
            }
            echo json_encode(['success' => true, 'from' => $from, 'to' => $to, 'holidays' => $customHolidays, 'data' => $result]);
            break;
        case 'dashboard':
            $date = $_GET['date'] ?? date('Y-m-d');
            $nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
            $dateHoliday = isHoliday($db, $date);
            $holidayName = null;
            if ($dateHoliday) { $hs = $db->prepare("SELECT name FROM public_holidays WHERE holiday_date = ?"); $hs->execute([$date]); $holidayName = $hs->fetchColumn(); }

            $allUsers = $db->query("SELECT id, badge_id, employee_id, name, calling_name, company_name, department_name, whatsapp_number FROM users WHERE employee_list_member = 1 ORDER BY COALESCE(NULLIF(employee_id, ''), badge_id) COLLATE NOCASE ASC")->fetchAll(PDO::FETCH_ASSOC);

            $companyFilter = trim((string)($_GET['company'] ?? ''));
            if ($companyFilter !== '') {
                $allUsers = array_values(array_filter($allUsers, function ($u) use ($companyFilter) {
                    return (string)($u['company_name'] ?? '') === $companyFilter;
                }));
            }

            $userIds = array_column($allUsers, 'id');
            $userIdList = $userIds ? implode(',', array_map('intval', $userIds)) : '0';

            $todayPunches = [];
            if ($userIds) {
                $stmt = $db->prepare("SELECT user_id, check_time, state FROM attendance WHERE user_id IN ($userIdList) AND check_time >= :date AND check_time < :next_date ORDER BY user_id, check_time");
                $stmt->execute([':date' => $date, ':next_date' => $nextDate]);
                $punches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($punches as $p) {
                    $todayPunches[$p['user_id']][] = $p;
                }
            }

            $todayData = [];
            $presentCount = 0; $lateCount = 0; $onTimeCount = 0; $absentCount = 0;
            foreach ($allUsers as $user) {
                $punches = $todayPunches[$user['id']] ?? [];
                $firstIn = null; $lastOut = null;
                foreach ($punches as $p) {
                    if (in_array((int)$p['state'], [0, 2, 4])) {
                        if (!$firstIn || $p['check_time'] < $firstIn) $firstIn = $p['check_time'];
                    } else {
                        if (!$lastOut || $p['check_time'] > $lastOut) $lastOut = $p['check_time'];
                    }
                }
                $hasPunches = !empty($punches);
                if ($dateHoliday) {
                    if ($hasPunches) {
                        $late = $firstIn ? isLateForUserOnDate($db, $user['id'], $date, $firstIn) : false;
                        $presentCount++;
                        if ($late) $lateCount++; else $onTimeCount++;
                        $todayData[] = [
                            'badge_id' => $user['badge_id'], 'employee_id' => normalizeEmployeeIdentifier($user['employee_id'] ?? $user['badge_id']),
                            'name' => $user['name'], 'calling_name' => $user['calling_name'], 'company_name' => $user['company_name'], 'department_name' => $user['department_name'],
                            'check_in' => formatAttendanceTime($firstIn, true), 'check_out' => formatAttendanceTime($lastOut, true),
                            'total_hours' => calculateTotalHours($firstIn, $lastOut), 'is_late' => $late, 'is_absent' => false,
                            'is_holiday' => true, 'holiday_name' => $holidayName, 'worked_on_holiday' => true,
                        ];
                    } else {
                        $todayData[] = [
                            'badge_id' => $user['badge_id'], 'employee_id' => normalizeEmployeeIdentifier($user['employee_id'] ?? $user['badge_id']),
                            'name' => $user['name'], 'calling_name' => $user['calling_name'], 'company_name' => $user['company_name'], 'department_name' => $user['department_name'],
                            'check_in' => null, 'check_out' => null,
                            'total_hours' => '0h 00m', 'is_late' => false, 'is_absent' => false,
                            'is_holiday' => true, 'holiday_name' => $holidayName, 'worked_on_holiday' => false,
                        ];
                    }
                } else {
                    $isAbsent = !$hasPunches;
                    $late = !$isAbsent && $firstIn ? isLateForUserOnDate($db, $user['id'], $date, $firstIn) : false;
                    if ($isAbsent) $absentCount++;
                    else { $presentCount++; if ($late) $lateCount++; else $onTimeCount++; }
                    $todayData[] = [
                        'badge_id' => $user['badge_id'], 'employee_id' => normalizeEmployeeIdentifier($user['employee_id'] ?? $user['badge_id']),
                        'name' => $user['name'], 'calling_name' => $user['calling_name'], 'company_name' => $user['company_name'], 'department_name' => $user['department_name'],
                        'check_in' => formatAttendanceTime($firstIn, true), 'check_out' => formatAttendanceTime($lastOut, true),
                        'total_hours' => calculateTotalHours($firstIn, $lastOut), 'is_late' => $late, 'is_absent' => $isAbsent,
                        'is_holiday' => false, 'holiday_name' => null, 'worked_on_holiday' => false,
                    ];
                }
            }

            $weekTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $d = (new DateTime($date))->modify("-{$i} days")->format('Y-m-d');
                $dn = (new DateTime($d))->modify('+1 day')->format('Y-m-d');
                $dayUsers = $userIds ? implode(',', array_map('intval', $userIds)) : '0';
                $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as present FROM attendance WHERE user_id IN ($dayUsers) AND check_time >= :date AND check_time < :next_date");
                $stmt->execute([':date' => $d, ':next_date' => $dn]);
                $present = (int)$stmt->fetchColumn();
                $totalEmp = count($allUsers);
                $weekTrend[] = ['date' => $d, 'day' => (new DateTime($d))->format('D'), 'present' => $present, 'absent' => $totalEmp - $present, 'total' => $totalEmp];
            }

            $companies = $db->query("SELECT DISTINCT company_name FROM users WHERE employee_list_member = 1 AND company_name != '' ORDER BY company_name")->fetchAll(PDO::FETCH_COLUMN);

            $companyStats = [];
            foreach ($companies as $comp) {
                $compUsers = array_values(array_filter($allUsers, function ($u) use ($comp) { return (string)$u['company_name'] === $comp; }));
                $compIds = array_column($compUsers, 'id');
                $compPresent = 0; $compLate = 0; $compOnTime = 0;
                foreach ($compIds as $uid) {
                    $punches = $todayPunches[$uid] ?? [];
                    if (!empty($punches)) {
                        $compPresent++;
                        $fi = null;
                        foreach ($punches as $p) { if (in_array((int)$p['state'], [0, 2, 4]) && (!$fi || $p['check_time'] < $fi)) $fi = $p['check_time']; }
                        if ($fi && date('H:i', strtotime($fi)) > (defined('GRACE_PERIOD_END') ? GRACE_PERIOD_END : '09:15')) $compLate++; else $compOnTime++;
                    }
                }
                $companyStats[] = ['name' => $comp, 'total' => count($compUsers), 'present' => $compPresent, 'late' => $compLate, 'on_time' => $compOnTime, 'absent' => count($compUsers) - $compPresent];
            }

            $depts = [];
            foreach ($allUsers as $u) {
                $dn = $u['department_name'] ?: 'Unassigned';
                if (!isset($depts[$dn])) $depts[$dn] = ['name' => $dn, 'total' => 0, 'present' => 0, 'late' => 0];
                $depts[$dn]['total']++;
                $punches = $todayPunches[$u['id']] ?? [];
                if (!empty($punches)) {
                    $depts[$dn]['present']++;
                    $fi = null;
                    foreach ($punches as $p) { if (in_array((int)$p['state'], [0, 2, 4]) && (!$fi || $p['check_time'] < $fi)) $fi = $p['check_time']; }
                    if ($fi && date('H:i', strtotime($fi)) > (defined('GRACE_PERIOD_END') ? GRACE_PERIOD_END : '09:15')) $depts[$dn]['late']++;
                }
            }
            $deptStats = array_values($depts);

            echo json_encode([
                'success' => true, 'date' => $date, 'is_holiday' => $dateHoliday, 'holiday_name' => $holidayName,
                'stats' => ['total' => count($allUsers), 'present' => $presentCount, 'late' => $lateCount, 'on_time' => $onTimeCount, 'absent' => $absentCount],
                'week_trend' => $weekTrend, 'companies' => $companyStats, 'departments' => $deptStats, 'company_names' => $companies,
                'data' => $todayData,
            ]);
            break;
        case 'dates':
            echo json_encode(['success' => true, 'dates' => getAttendanceDates($db)]);
            break;
        case 'users':
            echo json_encode(['success' => true, 'users' => getScopedUsers($db, currentUser()['id'], isAdminUser())]);
            break;
        case 'gender_stats':
            echo json_encode(['success' => true, 'gender_stats' => getGenderStats($db)]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
