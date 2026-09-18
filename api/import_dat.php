<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
requireLoginApi([2]);

function datHeaderKey($value) {
    return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $value)));
}

function datColumn($headers, $names) {
    foreach ($names as $name) {
        $key = datHeaderKey($name);
        if (isset($headers[$key])) return $headers[$key];
    }
    return null;
}

function datDelimiter($line) {
    // First check for consistent delimiters
    $counts = [
        "\t" => substr_count($line, "\t"),
        "," => substr_count($line, ","),
        ";" => substr_count($line, ";"),
        "|" => substr_count($line, "|")
    ];
    
    // If line has tabs, use tab as delimiter
    if ($counts["\t"] > 0) return "\t";
    // If line has commas, use comma
    if ($counts[","] > 0) return ",";
    // If line has semicolons, use semicolon
    if ($counts[";"] > 0) return ";";
    // If line has pipes, use pipe
    if ($counts["|"] > 0) return "|";
    
    // Otherwise use space (this handles space-separated values)
    return " ";
}

function datParseLineWithDelimiter($line, $delimiter) {
    if ($delimiter === " ") {
        // For space-delimited, split on multiple spaces and trim
        $fields = preg_split('/\s+/', trim($line));
        return $fields;
    } else {
        return str_getcsv(rtrim($line, "\r\n"), $delimiter);
    }
}

function datDateTime($date, $time) {
    $dateValue = trim((string) $date);
    $timeValue = trim((string) $time);
    if (preg_match('/^\d{1,4}[\/\-]\d{1,2}[\/\-]\d{1,4}[T\s]+\d{1,2}:\d{2}/', $dateValue)) {
        $timeValue = '';
    } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $dateValue, $parts)) {
        $year = (int) $parts[3];
        if ($year < 100) $year += 2000;
        $dateValue = sprintf('%04d-%02d-%02d', $year, (int) $parts[2], (int) $parts[1]);
    }
    if (is_numeric($dateValue) && (float) $dateValue > 20000) {
        $dateValue = gmdate('Y-m-d', (int) round(((float) $dateValue - 25569) * 86400));
    }
    if ($timeValue !== '' && is_numeric($timeValue) && (float) $timeValue >= 0 && (float) $timeValue < 1) {
        $seconds = (int) round((float) $timeValue * 86400);
        $timeValue = gmdate('H:i:s', $seconds);
    }
    $value = trim($dateValue . ' ' . $timeValue);
    if ($value === '') return null;
    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
}

function datFindDateTime($fields, $dateIndex, $timeIndex, $dateTimeIndex) {
    $date = $dateTimeIndex !== null ? ($fields[$dateTimeIndex] ?? '') : ($fields[$dateIndex] ?? '');
    $time = $dateTimeIndex !== null ? '' : ($fields[$timeIndex] ?? '');
    $result = datDateTime($date, $time);
    if ($result !== null) return $result;

    foreach ($fields as $index => $value) {
        $text = trim((string) $value);
        if (!preg_match('/\d{1,4}[\/\-]\d{1,2}[\/\-]\d{1,4}/', $text) && !(is_numeric($text) && (float) $text > 20000)) continue;
        foreach ($fields as $otherIndex => $otherValue) {
            if ($otherIndex === $index) continue;
            $candidate = datDateTime($text, (string) $otherValue);
            if ($candidate !== null) return $candidate;
        }
        $candidate = datDateTime($text, '');
        if ($candidate !== null) return $candidate;
    }
    return null;
}

function xlsxColumnNumber($reference) {
    preg_match('/^[A-Z]+/', strtoupper($reference), $match);
    $number = 0;
    foreach (str_split($match[0] ?? '') as $letter) $number = ($number * 26) + ord($letter) - 64;
    return max(0, $number - 1);
}

function xlsxRows($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Cannot open the XLSX file.');
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = simplexml_load_string($sharedXml);
        if ($shared !== false) {
            foreach ($shared->si as $item) $sharedStrings[] = (string) ($item->t ?? implode('', iterator_to_array($item->r->t ?? [])));
        }
    }
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ciah_xlsx_' . uniqid();
    if (!mkdir($tempDir) || !$zip->extractTo($tempDir, ['xl/worksheets/sheet1.xml'])) {
        $zip->close();
        throw new RuntimeException('Cannot read the first worksheet in the XLSX file.');
    }
    $zip->close();
    $sheetPath = $tempDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';
    $reader = new XMLReader();
    if (!$reader->open($sheetPath)) throw new RuntimeException('Cannot parse the XLSX worksheet.');
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') continue;
        $row = simplexml_load_string($reader->readOuterXml());
        $fields = [];
        foreach ($row->c as $cell) {
            $index = xlsxColumnNumber((string) $cell['r']);
            $type = (string) $cell['t'];
            $value = (string) ($cell->v ?? '');
            if ($type === 's') $value = $sharedStrings[(int) $value] ?? '';
            elseif ($type === 'inlineStr') $value = (string) ($cell->is->t ?? '');
            $fields[$index] = $value;
        }
        yield $fields;
    }
    $reader->close();
    @unlink($sheetPath);
    @rmdir(dirname($sheetPath));
    @rmdir(dirname(dirname($sheetPath)));
    @rmdir($tempDir);
}

function importEmployeeList($rows, $enrollIndex, $employeeIdIndex, $employeeNameIndex, $callingNameIndex, $companyIndex, $departmentIndex, $genderIndex, $db) {
    $find = $db->prepare('SELECT id FROM users WHERE badge_id = ? OR employee_id = ? LIMIT 1');
    $update = $db->prepare('UPDATE users SET employee_id = ?, name = ?, calling_name = ?, company_name = ?, department_name = ?, gender = ?, employee_list_member = 1 WHERE id = ?');
    $insert = $db->prepare('INSERT INTO users (id, badge_id, employee_id, name, calling_name, company_name, department_name, gender, employee_list_member, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0)');
    $findDepartment = $db->prepare('SELECT id FROM departments WHERE name = ? LIMIT 1');
    $insertDepartment = $db->prepare('INSERT INTO departments (name) VALUES (?)');
    $clearDepartments = $db->prepare('DELETE FROM user_departments WHERE user_id = ?');
    $linkDepartment = $db->prepare('INSERT OR IGNORE INTO user_departments (user_id, department_id) VALUES (?, ?)');
    $db->exec('UPDATE users SET employee_list_member = 0');
    $nextId = (int) $db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
    $imported = 0;
    $skipped = 0;
    $skippedNames = [];
    while ($rows->valid()) {
        $fields = $rows->current();
        $rows->next();
        $badgeId = trim((string) ($fields[$enrollIndex] ?? ''));
        if (ctype_digit($badgeId) && strlen($badgeId) < 5) $badgeId = str_pad($badgeId, 5, '0', STR_PAD_LEFT);
        $employeeId = $employeeIdIndex !== null ? trim((string) ($fields[$employeeIdIndex] ?? '')) : $badgeId;
        $employeeId = normalizeEmployeeIdentifier($employeeId);
        $employeeName = trim((string) ($fields[$employeeNameIndex] ?? ''));
        $callingName = $callingNameIndex !== null ? trim((string) ($fields[$callingNameIndex] ?? '')) : '';
        $companyName = $companyIndex !== null ? trim((string) ($fields[$companyIndex] ?? '')) : '';
        $departmentName = $departmentIndex !== null ? trim((string) ($fields[$departmentIndex] ?? '')) : '';
        $gender = $genderIndex !== null ? strtoupper(trim((string) ($fields[$genderIndex] ?? ''))) : '';
        
        // Normalize gender values
        if (in_array($gender, ['M', 'MALE', '1'])) {
            $gender = 'M';
        } elseif (in_array($gender, ['F', 'FEMALE', '2'])) {
            $gender = 'F';
        } else {
            $gender = '';
        }
        
        if ($badgeId === '' || $employeeName === '') {
            $skipped++;
            $label = $callingName !== '' ? $callingName : $employeeName;
            if ($label !== '') $skippedNames[] = $label;
            continue;
        }
        $find->execute([$badgeId, $employeeId]);
        $userId = $find->fetchColumn();
        if ($userId === false) {
            $userId = $nextId++;
            $insert->execute([$userId, $badgeId, $employeeId, $employeeName, $callingName, $companyName, $departmentName, $gender]);
        } else {
            $update->execute([$employeeId, $employeeName, $callingName, $companyName, $departmentName, $gender, $userId]);
        }
        $clearDepartments->execute([$userId]);
        if ($departmentName !== '') {
            $findDepartment->execute([$departmentName]);
            $departmentId = $findDepartment->fetchColumn();
            if ($departmentId === false) {
                $insertDepartment->execute([$departmentName]);
                $departmentId = $db->lastInsertId();
            }
            $linkDepartment->execute([$userId, $departmentId]);
        }
        $imported++;
    }
    return [$imported, $skipped, $skippedNames];
}

function retryOnSqliteLock(callable $callback, $maxAttempts = 5) {
    $attempt = 0;
    while (true) {
        try {
            return $callback();
        } catch (PDOException $e) {
            $message = strtolower($e->getMessage());
            $isLockError = strpos($message, 'locked') !== false || strpos($message, 'busy') !== false || strpos($message, 'database is locked') !== false;
            if (!$isLockError || ++$attempt >= $maxAttempts) {
                throw $e;
            }
            usleep(500000 * $attempt);
        }
    }
}

function importZKTecoAttendance($path, $db) {
    $data = file_get_contents($path);
    if ($data === false || strlen($data) < 40) throw new RuntimeException('The attlog.dat file is empty or invalid.');
    $imported = 0;
    $duplicates = 0;
    $recordSize = 40;
    $nextUserId = (int) $db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
    
    for ($offset = 0; $offset + $recordSize <= strlen($data); $offset += $recordSize) {
        $record = unpack('Vuid/Cyear/Cmonth/Cday/Chour/Cminute/Csecond/Cverify_state/Cwork_code', substr($data, $offset, $recordSize));
        if (!$record || $record['uid'] <= 0) continue;
        
        $badgeId = (string) $record['uid'];
        if (ctype_digit($badgeId) && strlen($badgeId) < 5) $badgeId = str_pad($badgeId, 5, '0', STR_PAD_LEFT);
        
        $year = $record['year'] + 2000;
        $month = $record['month'];
        $day = $record['day'];
        $hour = $record['hour'];
        $minute = $record['minute'];
        $second = $record['second'];
        
        $checkTime = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $checkTime) || strtotime($checkTime) === false) continue;
        
        $state = $record['verify_state'] ?? 1;
        
        // Find or create user
        $userStmt = $db->prepare('SELECT id FROM users WHERE badge_id = ? OR ltrim(badge_id, "0") = ltrim(?, "0") LIMIT 1');
        $userStmt->execute([$badgeId, $badgeId]);
        $userId = $userStmt->fetchColumn();
        
        if (!$userId) {
            $insertStmt = $db->prepare("INSERT INTO users (id, badge_id, name, role, employee_list_member) VALUES (?, ?, ?, 0, 1)");
            $insertStmt->execute([$nextUserId, $badgeId, "Employee {$badgeId}"]);
            $userId = $nextUserId++;
        }
        
        // Insert attendance
        $attendanceStmt = $db->prepare("INSERT INTO attendance (user_id, check_time, state) SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM attendance WHERE user_id = ? AND check_time = ? AND state = ?)");
        $attendanceStmt->execute([$userId, $checkTime, $state, $userId, $checkTime, $state]);
        
        if ($attendanceStmt->rowCount() > 0) {
            $imported++;
        } else {
            $duplicates++;
        }
    }
    
    return [$imported, $duplicates];
}


function importBinaryUsers($path, $db) {
    $data = file_get_contents($path);
    if ($data === false || strlen($data) < 64) throw new RuntimeException('The user.dat file is empty or invalid.');
    $userStmt = $db->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
    $inserted = 0;
    $updated = 0;
    $recordSize = 64;
    for ($offset = 0; $offset + $recordSize <= strlen($data); $offset += $recordSize) {
        $uid = unpack('V', substr($data, $offset, 4))[1];
        $name = trim(str_replace("\0", '', substr($data, $offset + 12, 28)));
        $badgeId = trim(str_replace("\0", '', substr($data, $offset + 50, 9)));
        if ($uid <= 0 || $name === '' || $badgeId === '') continue;
        $userStmt->execute([$uid]);
        $exists = (bool) $userStmt->fetchColumn();
        insertUser($db, ['uid' => $uid, 'id' => $badgeId, 'name' => $name, 'role' => 0]);
        if ($exists) $updated++; else $inserted++;
    }
    return [$inserted, $updated];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['dat_file'])) {
        throw new RuntimeException('Choose a DAT file first.');
    }
    $file = $_FILES['dat_file'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['dat', 'txt', 'csv', 'xlsx'], true)) throw new RuntimeException('Only DAT, TXT, CSV, and XLSX files are supported.');
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('File upload failed.');
    if ($file['size'] > 512 * 1024 * 1024) throw new RuntimeException('The file is larger than the 512 MB limit.');

    if ($extension === 'dat') {
        $sample = file_get_contents($file['tmp_name'], false, null, 0, 256);
        if ($sample !== false && substr_count($sample, "\0") > 10) {
            // Binary user.dat format
            $db = getDB();
            try {
                [$insertedUsers, $updatedUsers] = retryOnSqliteLock(function () use ($db, $file) {
                    $db->beginTransaction();
                    try {
                        $result = importBinaryUsers($file['tmp_name'], $db);
                        $db->commit();
                        return $result;
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        throw $e;
                    }
                });
                echo json_encode(['success' => true, 'message' => "Imported {$insertedUsers} users and updated {$updatedUsers} users. This user.dat file contains no attendance date/time records.", 'stats' => ['users' => $insertedUsers + $updatedUsers, 'attendance' => 0]]);
                exit;
            } catch (Throwable $e) {
                if (isset($db) && $db->inTransaction()) $db->rollBack();
                throw $e;
            }
        } else {
            // Treat as text attendance log file (attlog.dat format)
            // Rename to .txt so it gets processed as CSV
            $_FILES['dat_file']['name'] = preg_replace('/\.dat$/i', '.txt', $file['name']);
            $extension = 'txt';
        }
    }

    $isXlsx = $extension === 'xlsx';
    $rows = $isXlsx ? xlsxRows($file['tmp_name']) : null;
    $handle = $isXlsx ? null : fopen($file['tmp_name'], 'rb');
    if (!$isXlsx && !$handle) throw new RuntimeException('Cannot read the uploaded file.');

    if ($isXlsx) {
        if (!$rows->valid()) throw new RuntimeException('The XLSX file is empty.');
        $firstFields = $rows->current();
        $headerRowFound = false;
        for ($headerAttempt = 0; $headerAttempt < 25 && $rows->valid(); $headerAttempt++) {
            $candidateHeaders = array_map('datHeaderKey', $rows->current());
            $hasEmployeeIdHeader = (bool)array_filter($candidateHeaders, function ($header) { return in_array($header, ['enrollno', 'empno', 'epfno', 'empnoepfno'], true) || strpos($header, 'empnoepfno') === 0; });
            if ($hasEmployeeIdHeader && (in_array('employeename', $candidateHeaders, true) || in_array('name', $candidateHeaders, true))) {
                $firstFields = $rows->current();
                $headerRowFound = true;
                break;
            }
            $rows->next();
        }
        if (!$headerRowFound) throw new RuntimeException('The XLSX file must contain an employee ID and employee name column.');
    } else {
        $firstLine = fgets($handle);
        if ($firstLine === false) throw new RuntimeException('The uploaded file is empty.');
        $delimiter = datDelimiter($firstLine);
        $firstFields = datParseLineWithDelimiter($firstLine, $delimiter);
    }
    $headerNames = array_map('datHeaderKey', $firstFields);
    $hasHeader = (bool) array_intersect($headerNames, ['pin', 'userid', 'uid', 'user', 'usercode', 'employeeid', 'employeeno', 'empno', 'epfno', 'empnopepfno', 'enrollno', 'employeename', 'name', 'callingname', 'company', 'companyname', 'dept', 'department', 'departmentname', 'date', 'time', 'datetime', 'checktime']);
        if (!$hasHeader) foreach ($headerNames as $header) if (strpos($header, 'empnoepfno') === 0) { $hasHeader = true; break; }
    $headers = [];
    if ($hasHeader) {
        foreach ($headerNames as $index => $header) $headers[$header] = $index;
    } elseif (!$isXlsx) {
        rewind($handle);
    }

    $enrollIndex = datColumn($headers, ['enroll no', 'enroll number', 'enroll_no', 'enrollno']);
    if ($enrollIndex === null) $enrollIndex = $headers['empnopepfnoforvft'] ?? null;
    if ($enrollIndex === null) $enrollIndex = datColumn($headers, ['emp_no/epf_no', 'empnopepfno', 'employee no', 'employee id', 'emp no', 'emp number', 'empno', 'emp_no', 'epf no', 'epf number', 'epfno', 'epf_no']);
    $employeeIdIndex = datColumn($headers, ['emp_no/epf_no', 'empnopepfno', 'employee no', 'employee id', 'emp no', 'emp number', 'empno', 'emp_no', 'epf no', 'epf number', 'epfno', 'epf_no']);
    if ($employeeIdIndex === null) $employeeIdIndex = $headers['employeeid'] ?? null;
    $employeeNameIndex = datColumn($headers, ['employee name', 'full name', 'name']);
    $callingNameIndex = datColumn($headers, ['calling name', 'preferred name']);
    $companyIndex = datColumn($headers, ['company', 'company name']);
    $departmentIndex = datColumn($headers, ['dept', 'department name', 'department', 'department_name']);
    $genderIndex = datColumn($headers, ['m/f', 'M/F', 'gender', 'sex']);
    if ($isXlsx && $enrollIndex !== null && $employeeNameIndex !== null) {
        $db = getDB();
        if ($hasHeader) $rows->next();
        try {
            [$importedEmployees, $skippedEmployees, $skippedNames, $removedEmployees] = retryOnSqliteLock(function () use ($db, $rows, $enrollIndex, $employeeIdIndex, $employeeNameIndex, $callingNameIndex, $companyIndex, $departmentIndex, $genderIndex) {
                $db->beginTransaction();
                try {
                    $result = importEmployeeList($rows, $enrollIndex, $employeeIdIndex, $employeeNameIndex, $callingNameIndex, $companyIndex, $departmentIndex, $genderIndex, $db);
                    $removedEmployees = (int)$db->query("SELECT COUNT(*) FROM users WHERE employee_list_member = 0 AND NOT EXISTS (SELECT 1 FROM attendance WHERE attendance.user_id = users.id)")->fetchColumn();
                    $db->exec("DELETE FROM users WHERE employee_list_member = 0 AND NOT EXISTS (SELECT 1 FROM attendance WHERE attendance.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM departments WHERE departments.hod_user_id = users.id) AND NOT EXISTS (SELECT 1 FROM user_departments WHERE user_departments.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM rosters WHERE rosters.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM leaves WHERE leaves.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM overtime WHERE overtime.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM notifications WHERE notifications.user_id = users.id)");
                    $db->commit();
                    return [$result[0], $result[1], $result[2], $removedEmployees];
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    throw $e;
                }
            });
            echo json_encode(['success' => true, 'message' => "Imported {$importedEmployees} employee records; {$removedEmployees} unrelated users removed; {$skippedEmployees} incomplete rows skipped.", 'skipped_names' => $skippedNames, 'stats' => ['users' => $importedEmployees, 'removed_users' => $removedEmployees, 'skipped' => $skippedEmployees, 'attendance' => 0]]);
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    $pinIndex = datColumn($headers, ['pin', 'userid', 'user id', 'uid', 'user', 'user code', 'employee id', 'employee no', 'emp id', 'emp no', 'emp_no', 'empno', 'epf no', 'epf_no', 'epfno', 'emp_no/epf_no', 'empnopepfno', 'id']);
    $dateIndex = datColumn($headers, ['date', 'attendance date', 'check date']);
    $timeIndex = datColumn($headers, ['time', 'attendance time', 'check time']);
    $dateTimeIndex = datColumn($headers, ['datetime', 'date time', 'timestamp', 'checktime']);
    $stateIndex = datColumn($headers, ['state', 'status', 'attendance state']);
    if ($pinIndex === null) $pinIndex = 0;
    if ($dateIndex === null && $dateTimeIndex === null) $dateIndex = 1;
    if ($timeIndex === null && $dateTimeIndex === null) $timeIndex = 2;
    if ($stateIndex === null) $stateIndex = 3;

    $db = getDB();
    $userStmt = $db->prepare("SELECT id FROM users WHERE badge_id = ? OR employee_id = ? OR CAST(id AS TEXT) = ? OR ltrim(badge_id, '0') = ltrim(?, '0') OR ltrim(employee_id, '0') = ltrim(?, '0') LIMIT 1");
    $attendanceStmt = $db->prepare("INSERT INTO attendance (user_id, check_time, state) SELECT :user_id, :check_time, :state WHERE NOT EXISTS (SELECT 1 FROM attendance WHERE user_id = :existing_user_id AND check_time = :existing_check_time AND state = :existing_state)");
    $imported = 0;
    $duplicates = 0;
    $skipped = 0;
    $invalidRows = 0;
    $unmatchedRows = 0;
    $createdUsers = 0;
    $lineNumber = $hasHeader ? 1 : 0;
    if ($isXlsx && $hasHeader) $rows->next();

    try {
        retryOnSqliteLock(function () use ($db, $handle, $isXlsx, $rows, $hasHeader, $pinIndex, $dateIndex, $timeIndex, $dateTimeIndex, $stateIndex, $delimiter, &$imported, &$duplicates, &$skipped, &$invalidRows, &$unmatchedRows, &$lineNumber, $userStmt, $attendanceStmt) {
            $db->beginTransaction();
            try {
                while (true) {
                    if ($isXlsx) {
                        if (!$rows->valid()) break;
                        $fields = $rows->current();
                        $rows->next();
                    } else {
                        $line = fgets($handle);
                        if ($line === false) break;
                        if (trim($line) === '') continue;
                        $fields = datParseLineWithDelimiter($line, $delimiter);
                    }
                    $lineNumber++;
                    $pin = trim((string) ($fields[$pinIndex] ?? ''));
                    $date = $dateTimeIndex !== null ? (string) ($fields[$dateTimeIndex] ?? '') : (string) ($fields[$dateIndex] ?? '');
                    $time = $dateTimeIndex !== null ? '' : (string) ($fields[$timeIndex] ?? '');
                    $checkTime = datFindDateTime($fields, $dateIndex, $timeIndex, $dateTimeIndex);
                    if ($pin === '' || $checkTime === null) {
                        $skipped++;
                        $invalidRows++;
                        continue;
                    }
                    $state = isset($fields[$stateIndex]) && is_numeric(trim($fields[$stateIndex])) ? (int) trim($fields[$stateIndex]) : 1;
                    $userStmt->execute([$pin, $pin, $pin, $pin, $pin]);
                    $userId = $userStmt->fetchColumn();
                    if (!$userId) {
                        // Create user automatically if not found
                        $nextId = (int) $db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
                        $badgeId = $pin;
                        if (ctype_digit($badgeId) && strlen($badgeId) < 5) $badgeId = str_pad($badgeId, 5, '0', STR_PAD_LEFT);
                        $insertUserStmt = $db->prepare("INSERT INTO users (id, badge_id, name, role, employee_list_member) VALUES (?, ?, ?, 0, 1)");
                        $insertUserStmt->execute([$nextId, $badgeId, "Employee {$pin}"]);
                        $userId = $nextId;
                        $createdUsers++;
                    }
                    $attendanceStmt->execute([':user_id' => $userId, ':check_time' => $checkTime, ':state' => $state, ':existing_user_id' => $userId, ':existing_check_time' => $checkTime, ':existing_state' => $state]);
                    if ($attendanceStmt->rowCount() > 0) $imported++; else $duplicates++;
                }
                $db->commit();
                return true;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
        });
    } finally {
        if ($handle) fclose($handle);
    }

    echo json_encode(['success' => true, 'message' => "Imported {$imported} records; {$duplicates} duplicates skipped; created {$createdUsers} placeholder users; {$invalidRows} invalid rows and {$unmatchedRows} unmatched users skipped.", 'stats' => ['imported' => $imported, 'duplicates' => $duplicates, 'created_users' => $createdUsers, 'skipped' => $skipped, 'invalid' => $invalidRows, 'unmatched' => $unmatchedRows]]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('DAT Import Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
