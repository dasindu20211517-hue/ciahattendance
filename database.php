<?php
require_once __DIR__ . '/config.php';

function normalizeEmployeeIdentifier($value) {
    $value = trim((string)$value);
    return ctype_digit($value) && strlen($value) < 5 ? str_pad($value, 5, '0', STR_PAD_LEFT) : $value;
}

function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 30,
        ]);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA synchronous=NORMAL');
        $db->exec('PRAGMA busy_timeout = 30000');
        $db->exec('PRAGMA foreign_keys=ON');
        initSchema($db);
    }
    return $db;
}

function initSchema($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        badge_id TEXT UNIQUE NOT NULL,
        employee_id TEXT,
        name TEXT NOT NULL,
        calling_name TEXT DEFAULT '',
            company_name TEXT DEFAULT '',
        department_name TEXT DEFAULT '',
        gender TEXT DEFAULT '',
        employee_list_member INTEGER DEFAULT 0,
        role INTEGER DEFAULT 0,
        username TEXT UNIQUE,
        password_hash TEXT
    )");
    $userColumns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('calling_name', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN calling_name TEXT DEFAULT ''");
    if (!in_array('employee_id', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN employee_id TEXT");
        if (!in_array('company_name', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN company_name TEXT DEFAULT ''");
    if (!in_array('department_name', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN department_name TEXT DEFAULT ''");
    if (!in_array('gender', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN gender TEXT DEFAULT ''");
    if (!in_array('employee_list_member', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN employee_list_member INTEGER DEFAULT 0");
    if (!in_array('username', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN username TEXT");
    if (!in_array('password_hash', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN password_hash TEXT");
    if (!in_array('whatsapp_number', $userColumns, true)) $db->exec("ALTER TABLE users ADD COLUMN whatsapp_number TEXT DEFAULT ''");
    // Email column removed - using WhatsApp integration only
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username)");
    $db->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        check_time DATETIME NOT NULL,
        state INTEGER NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_att_date ON attendance(check_time)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_att_user ON attendance(user_id, check_time)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_att_dedup ON attendance(user_id, check_time, state)");

    $db->exec("CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        hod_user_id INTEGER,
        FOREIGN KEY (hod_user_id) REFERENCES users(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS user_departments (
        user_id INTEGER NOT NULL,
        department_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, department_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (department_id) REFERENCES departments(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS shifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        is_rostered INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS rosters (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        effective_from DATE NOT NULL,
        effective_to DATE,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS roster_days (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        roster_id INTEGER NOT NULL,
        day_of_week INTEGER NOT NULL,
        shift_id INTEGER,
        is_rest_day INTEGER DEFAULT 0,
        FOREIGN KEY (roster_id) REFERENCES rosters(id) ON DELETE CASCADE,
        FOREIGN KEY (shift_id) REFERENCES shifts(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS roster_overrides (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        roster_id INTEGER NOT NULL,
        override_date DATE NOT NULL,
        shift_id INTEGER,
        is_rest_day INTEGER DEFAULT 0,
        FOREIGN KEY (roster_id) REFERENCES rosters(id) ON DELETE CASCADE,
        FOREIGN KEY (shift_id) REFERENCES shifts(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS leaves (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        leave_type TEXT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        reason TEXT,
        status TEXT DEFAULT 'pending',
        approved_by INTEGER,
        rejection_reason TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS leave_balances (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        leave_type TEXT NOT NULL,
        year INTEGER NOT NULL,
        entitled INTEGER NOT NULL DEFAULT 0,
        used INTEGER NOT NULL DEFAULT 0,
        UNIQUE(user_id, leave_type, year),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS overtime (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        ot_date DATE NOT NULL,
        hours REAL NOT NULL,
        reason TEXT,
        status TEXT DEFAULT 'pending',
        approved_by INTEGER,
        rejection_reason TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS public_holidays (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        holiday_date DATE UNIQUE NOT NULL,
        name TEXT NOT NULL,
        year INTEGER NOT NULL,
        is_poya INTEGER DEFAULT 0
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        channel TEXT NOT NULL,
        subject TEXT,
        message TEXT NOT NULL,
        status TEXT DEFAULT 'sent',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_leaves_user ON leaves(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_ot_user ON overtime(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id)");

    // Form Management Tables
    $db->exec("CREATE TABLE IF NOT EXISTS form_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT UNIQUE NOT NULL,
        form_type TEXT NOT NULL,
        user_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME,
        is_active INTEGER DEFAULT 1,
        form_data TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS form_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_link_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        form_type TEXT NOT NULL,
        submission_data TEXT NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip_address TEXT,
        user_agent TEXT,
        FOREIGN KEY (form_link_id) REFERENCES form_links(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_links_token ON form_links(token)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_links_user ON form_links(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_subs_form ON form_submissions(form_link_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_form_subs_user ON form_submissions(user_id)");

}

function seedShifts($db) {
    $count = $db->query("SELECT COUNT(*) FROM shifts")->fetchColumn();
    if ($count > 0) return;
    $shifts = [
        ['General', '09:00', '17:00', 0],
        ['Morning 7-4', '07:00', '16:00', 1],
        ['Afternoon 1-10', '13:00', '22:00', 1],
        ['Extended 10-7', '10:00', '19:00', 1],
        ['Long 7-7', '07:00', '19:00', 1],
        ['Night 7pm-7am', '19:00', '07:00', 1],
    ];
    $stmt = $db->prepare("INSERT INTO shifts (name, start_time, end_time, is_rostered) VALUES (?, ?, ?, ?)");
    foreach ($shifts as $s) $stmt->execute($s);
}

function insertUser($db, $user) {
    $userId = (int) $user['uid'];
    $badgeId = (string) ($user['id'] ?? $user['user_id'] ?? '');
    if (ctype_digit(trim($badgeId)) && strlen(trim($badgeId)) < 5) $badgeId = str_pad(trim($badgeId), 5, '0', STR_PAD_LEFT);
    if ($userId <= 0 || $badgeId === '' || trim((string) ($user['name'] ?? '')) === '') return;
    $params = [':id' => $userId, ':badge_id' => $badgeId, ':name' => $user['name'], ':calling_name' => $user['calling_name'] ?? '', ':department_name' => $user['department_name'] ?? '', ':employee_list_member' => (int)($user['employee_list_member'] ?? 0), ':role' => $user['role'] ?? 0];
    $update = $db->prepare("UPDATE users SET badge_id = :badge_id, name = CASE WHEN employee_list_member = 1 THEN name ELSE :name END, calling_name = CASE WHEN employee_list_member = 1 THEN calling_name ELSE :calling_name END, department_name = CASE WHEN employee_list_member = 1 THEN department_name ELSE :department_name END, employee_list_member = employee_list_member, role = :role WHERE id = :id");
    $update->execute($params);
    $exists = $db->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
    $exists->execute([$userId]);
    if (!$exists->fetchColumn()) {
        $insert = $db->prepare("INSERT INTO users (id, badge_id, name, calling_name, department_name, employee_list_member, role) VALUES (:id, :badge_id, :name, :calling_name, :department_name, :employee_list_member, :role)");
        $insert->execute($params);
    }
}

function insertAttendance($db, $record, $stmt = null) {
    $checkTime = $record['time'] ?? $record['record_time'] ?? null;
    $userId = (int) ($record['uid'] ?? 0);
    if ($userId <= 0 || empty($checkTime) || strtotime($checkTime) === false) return;
    if ($stmt === null) {
        $stmt = $db->prepare("INSERT INTO attendance (user_id, check_time, state) SELECT :user_id, :check_time, :state WHERE EXISTS (SELECT 1 FROM users WHERE id = :user_id3) AND NOT EXISTS (SELECT 1 FROM attendance WHERE user_id = :user_id2 AND check_time = :check_time2 AND state = :state2)");
    }
    $stmt->execute([':user_id' => $userId, ':check_time' => $checkTime, ':state' => $record['state'], ':user_id2' => $userId, ':check_time2' => $checkTime, ':state2' => $record['state'], ':user_id3' => $userId]);
}

function repairLegacyDeviceImport($db) {
    $hasInvalidAttendance = (int) $db->query("SELECT COUNT(*) FROM attendance WHERE user_id = 0")->fetchColumn() > 0;
    if (!$hasInvalidAttendance) return false;

    $db->beginTransaction();
    try {
        $db->exec("DELETE FROM attendance WHERE user_id = 0");
        $db->exec("DELETE FROM users WHERE NOT EXISTS (SELECT 1 FROM attendance WHERE attendance.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM departments WHERE departments.hod_user_id = users.id) AND NOT EXISTS (SELECT 1 FROM user_departments WHERE user_departments.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM rosters WHERE rosters.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM leaves WHERE leaves.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM overtime WHERE overtime.user_id = users.id) AND NOT EXISTS (SELECT 1 FROM notifications WHERE notifications.user_id = users.id)");
        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function getDailySummary($db, $date, $includeAbsent = false) {
    $nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
    $joinType = $includeAbsent ? 'LEFT JOIN' : 'INNER JOIN';
    $having = $includeAbsent ? '' : 'HAVING COUNT(a.id) > 0';
    $stmt = $db->prepare("SELECT u.badge_id as badge_id, u.employee_id, u.company_name, u.department_name, u.gender, u.name AS official_name, u.calling_name, u.whatsapp_number, COALESCE(NULLIF(u.calling_name, ''), u.name) as name,
           MIN(CASE WHEN a.state IN (0, 2, 4) THEN a.check_time END) as first_checkin,
           u.name AS official_name, u.calling_name,
        MAX(CASE WHEN a.state IN (1, 3, 5) THEN a.check_time END) as last_checkout,
        COUNT(a.id) AS attendance_count
        FROM users u
        {$joinType} attendance a ON u.id = a.user_id AND a.check_time >= :date AND a.check_time < :next_date AND a.user_id IN (SELECT id FROM users)
        WHERE u.employee_list_member = 1
        GROUP BY u.id, u.name, u.badge_id, u.employee_id, u.company_name, u.department_name, u.gender, u.calling_name, u.whatsapp_number {$having} ORDER BY COALESCE(NULLIF(u.employee_id, ''), u.badge_id) COLLATE NOCASE ASC");
    $stmt->execute([':date' => $date, ':next_date' => $nextDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlyReport($db, $year, $month) {
    $stmt = $db->prepare("SELECT u.badge_id as badge_id, u.employee_id, u.company_name, u.department_name, u.name AS official_name, u.calling_name, u.whatsapp_number, COALESCE(NULLIF(u.calling_name, ''), u.name) as name, DATE(a.check_time) as date,
        MIN(CASE WHEN a.state IN (0, 2, 4) THEN a.check_time END) as first_checkin,
        MAX(CASE WHEN a.state IN (1, 3, 5) THEN a.check_time END) as last_checkout
        FROM users u
        INNER JOIN attendance a ON u.id = a.user_id AND strftime('%Y-%m', a.check_time) = :ym AND a.user_id IN (SELECT id FROM users)
        WHERE u.employee_list_member = 1
        GROUP BY u.id, u.name, u.badge_id, u.employee_id, u.department_name, u.calling_name, u.whatsapp_number, DATE(a.check_time) ORDER BY COALESCE(NULLIF(u.employee_id, ''), u.badge_id) COLLATE NOCASE ASC, date");
    $stmt->execute([':ym' => sprintf('%04d-%02d', $year, $month)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlySummary($db, $year, $month) {
    $ym = sprintf('%04d-%02d', $year, $month);
    $stmt = $db->prepare("SELECT u.badge_id as badge_id, u.company_name, u.department_name, u.name AS official_name, u.calling_name, COALESCE(NULLIF(u.calling_name, ''), u.name) as name, COUNT(DISTINCT DATE(a.check_time)) as days_present FROM users u INNER JOIN attendance a ON u.id = a.user_id AND strftime('%Y-%m', a.check_time) = :ym AND a.user_id IN (SELECT id FROM users) WHERE u.employee_list_member = 1 GROUP BY u.id, u.name, u.badge_id, u.company_name, u.department_name, u.calling_name ORDER BY CAST(u.badge_id AS INTEGER) ASC");
    $stmt->execute([':ym' => $ym]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlyWorkingDays($db, $year, $month) {
    $ym = sprintf('%04d-%02d', $year, $month);
    $stmt = $db->prepare("SELECT COUNT(DISTINCT DATE(a.check_time)) FROM attendance a WHERE a.user_id IN (SELECT id FROM users) AND strftime('%Y-%m', a.check_time) = :ym");
    $stmt->execute([':ym' => $ym]);
    return (int) $stmt->fetchColumn();
}

function getWeeklyReport($db, $date) {
    $d = new DateTime($date);
    $d->modify('monday this week');
    $monday = $d->format('Y-m-d');
    $d->modify('sunday this week');
    $sunday = $d->format('Y-m-d');
    $stmt = $db->prepare("SELECT u.badge_id as badge_id, u.employee_id, u.company_name, u.department_name, u.name AS official_name, u.calling_name, COALESCE(NULLIF(u.calling_name, ''), u.name) as name, DATE(a.check_time) as date,
        MIN(CASE WHEN a.state IN (0, 2, 4) THEN a.check_time END) as first_checkin,
        MAX(CASE WHEN a.state IN (1, 3, 5) THEN a.check_time END) as last_checkout
        FROM users u
        INNER JOIN attendance a ON u.id = a.user_id AND DATE(a.check_time) BETWEEN :mon AND :sun AND a.user_id IN (SELECT id FROM users)
        WHERE u.employee_list_member = 1
        GROUP BY u.id, u.name, u.badge_id, u.department_name, u.calling_name, DATE(a.check_time) ORDER BY CAST(u.badge_id AS INTEGER) ASC, date");
    $stmt->execute([':mon' => $monday, ':sun' => $sunday]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomReport($db, $from, $to) {
    $stmt = $db->prepare("SELECT u.badge_id as badge_id, u.employee_id, u.company_name, u.department_name, u.name AS official_name, u.calling_name, u.whatsapp_number, COALESCE(NULLIF(u.calling_name, ''), u.name) as name, DATE(a.check_time) as date,
        MIN(CASE WHEN a.state IN (0, 2, 4) THEN a.check_time END) as first_checkin,
        MAX(CASE WHEN a.state IN (1, 3, 5) THEN a.check_time END) as last_checkout
        FROM users u
        INNER JOIN attendance a ON u.id = a.user_id AND DATE(a.check_time) BETWEEN :from AND :to AND a.user_id IN (SELECT id FROM users)
        WHERE u.employee_list_member = 1
        GROUP BY u.id, u.name, u.badge_id, u.department_name, u.calling_name, u.whatsapp_number, DATE(a.check_time) ORDER BY CAST(u.badge_id AS INTEGER) ASC, date");
    $stmt->execute([':from' => $from, ':to' => $to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAttendanceDates($db) {
    return $db->query("SELECT DISTINCT DATE(check_time) as date FROM attendance WHERE user_id IN (SELECT id FROM users) ORDER BY date DESC")->fetchAll(PDO::FETCH_COLUMN);
}

function getAllUsers($db) {
    $users = $db->query("SELECT *, COALESCE(NULLIF(calling_name, ''), name) AS display_name FROM users WHERE employee_list_member = 1 ORDER BY COALESCE(NULLIF(employee_id, ''), badge_id) COLLATE NOCASE ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$user) $user['employee_id'] = normalizeEmployeeIdentifier($user['employee_id'] ?? '');
    return $users;
}

function getGenderStats($db) {
    $stmt = $db->query("SELECT 
        SUM(CASE WHEN UPPER(gender) = 'M' OR UPPER(gender) = 'MALE' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN UPPER(gender) = 'F' OR UPPER(gender) = 'FEMALE' THEN 1 ELSE 0 END) as female_count,
        COUNT(*) as total_count
        FROM users WHERE employee_list_member = 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getScopedUsers($db, $userId, $isAdmin) {
    if ($isAdmin) return getAllUsers($db);
    $stmt = $db->prepare('SELECT DISTINCT u.* FROM users u INNER JOIN user_departments ud ON ud.user_id=u.id INNER JOIN departments d ON d.id=ud.department_id WHERE d.hod_user_id=? ORDER BY u.name');
    $stmt->execute([(int)$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function isLate($checkTime, $workStart = WORK_START_TIME) {
    if (!$checkTime) return false;
    $time = date('H:i', strtotime($checkTime));
    
    // Grace period: On time if before 9:00 AM, Late if after 9:15 AM
    // Between 9:00-9:15 AM is considered "On Time" (grace period)
    $gracePeriodEnd = defined('GRACE_PERIOD_END') ? GRACE_PERIOD_END : '09:15';
    return $time > $gracePeriodEnd;
}

function isLateForUserOnDate($db, $userId, $date, $checkTime) {
    if (!$checkTime) return false;
    
    // Get user's shift for this date
    $shiftInfo = getShiftForDate($db, $userId, $date);
    
    if ($shiftInfo && !$shiftInfo['is_rest_day'] && $shiftInfo['shift']) {
        // Use shift start time if available
        $shiftStart = $shiftInfo['shift']['start_time'] ?? WORK_START_TIME;
        return isLate($checkTime, $shiftStart);
    }
    
    // Fall back to default work start time
    return isLate($checkTime, WORK_START_TIME);
}

function calculateTotalHours($checkin, $checkout) {
    if (!$checkin || !$checkout) return '-';
    $start = strtotime($checkin);
    $end = strtotime($checkout);
    if ($start === false || $end === false) return '-';
    if ($end < $start) {
        $end += 86400;
    }
    $diff = $end - $start;
    if ($diff < 0) return '-';
    return sprintf('%dh %02dm', floor($diff / 3600), floor(($diff % 3600) / 60));
}

function getAttendanceStatus($checkin, $checkout) {
    if (!$checkin && !$checkout) return 'No attendance';
    if (!$checkin) return 'Missing Check In';
    if (!$checkout) return 'Missing Check Out';
    $start = strtotime($checkin);
    $end = strtotime($checkout);
    if ($start === false || $end === false) return 'Invalid record';
    if ($end < $start) return 'Overnight Shift';
    $hours = ($end - $start) / 3600;
    if ($hours >= 12) return '12h Shift';
    return 'Normal Shift';
}

function getAttendanceStatusWithTime($checkin, $checkout, $date = null) {
    if (!$checkin && !$checkout) return 'No attendance';
    
    // Check if work day is over or if it's a past date
    $workDayOver = false;
    $isPastDate = false;
    
    if ($date) {
        $isPastDate = date('Y-m-d') > $date;
        $workEndTime = $date . ' ' . WORK_END_TIME;
        $workDayOver = time() > strtotime($workEndTime);
    }
    
    // If no check-in
    if (!$checkin) {
        if ($workDayOver || $isPastDate) {
            return 'Missing Check In';
        } else {
            return 'Present'; // During work day
        }
    }
    
    // If no check-out
    if (!$checkout) {
        if ($workDayOver || $isPastDate) {
            return 'Missing Check Out';
        } else {
            // During work day, show if they were late or on time
            $checkinTime = date('H:i', strtotime($checkin));
            return $checkinTime > (defined('GRACE_PERIOD_END') ? GRACE_PERIOD_END : '09:15') ? 'Late' : 'On Time';
        }
    }
    
    // Both check-in and check-out exist
    $start = strtotime($checkin);
    $end = strtotime($checkout);
    if ($start === false || $end === false) return 'Invalid record';
    if ($end < $start) return 'Overnight Shift';
    $hours = ($end - $start) / 3600;
    if ($hours >= 12) return '12h Shift';
    
    // Show if they were late or on time based on check-in
    $checkinTime = date('H:i', strtotime($checkin));
    return $checkinTime > (defined('GRACE_PERIOD_END') ? GRACE_PERIOD_END : '09:15') ? 'Late' : 'On Time';
}

function formatAttendanceTime($value, $withSeconds = true) {
    if (!$value) return '-';
    $time = strtotime($value);
    if ($time === false) return '-';
    return date($withSeconds ? 'h:i:s A' : 'h:i A', $time);
}

function getAllDepartments($db) {
    return $db->query("SELECT d.*, u.name as hod_name FROM departments d LEFT JOIN users u ON d.hod_user_id = u.id ORDER BY d.name")->fetchAll(PDO::FETCH_ASSOC);
}

function createDepartment($db, $name, $hodUserId) {
    $stmt = $db->prepare("INSERT INTO departments (name, hod_user_id) VALUES (?, ?)");
    $stmt->execute([$name, $hodUserId ?: null]);
    return $db->lastInsertId();
}

function updateDepartment($db, $id, $name, $hodUserId) {
    $stmt = $db->prepare("UPDATE departments SET name=?, hod_user_id=? WHERE id=?");
    $stmt->execute([$name, $hodUserId ?: null, $id]);
}

function deleteDepartment($db, $id) {
    $db->prepare("DELETE FROM user_departments WHERE department_id=?")->execute([$id]);
    $db->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
}

function getDepartmentUsers($db, $deptId) {
    $stmt = $db->prepare("SELECT u.* FROM users u INNER JOIN user_departments ud ON u.id = ud.user_id WHERE ud.department_id = ? ORDER BY u.name");
    $stmt->execute([$deptId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function assignUserToDepartment($db, $userId, $deptId) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO user_departments (user_id, department_id) VALUES (?, ?)");
    $stmt->execute([$userId, $deptId]);
}

function getUserDepartment($db, $userId) {
    $stmt = $db->prepare("SELECT d.* FROM departments d INNER JOIN user_departments ud ON d.id = ud.department_id WHERE ud.user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllShifts($db) {
    return $db->query("SELECT * FROM shifts ORDER BY is_rostered, name")->fetchAll(PDO::FETCH_ASSOC);
}

function getActiveShifts($db) {
    return $db->query("SELECT * FROM shifts WHERE is_active = 1 ORDER BY is_rostered, name")->fetchAll(PDO::FETCH_ASSOC);
}

function createShift($db, $name, $startTime, $endTime, $isRostered) {
    $stmt = $db->prepare("INSERT INTO shifts (name, start_time, end_time, is_rostered) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $startTime, $endTime, $isRostered]);
    return $db->lastInsertId();
}

function updateShift($db, $id, $name, $startTime, $endTime, $isRostered, $isActive) {
    $stmt = $db->prepare("UPDATE shifts SET name=?, start_time=?, end_time=?, is_rostered=?, is_active=? WHERE id=?");
    $stmt->execute([$name, $startTime, $endTime, $isRostered, $isActive, $id]);
}

function deleteShift($db, $id) {
    $db->prepare("DELETE FROM shifts WHERE id=? AND id NOT IN (SELECT id FROM shifts WHERE is_rostered=0 LIMIT 1)")->execute([$id]);
}

function getUserRoster($db, $userId, $date) {
    $stmt = $db->prepare("SELECT * FROM rosters WHERE user_id=? AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) ORDER BY effective_from DESC LIMIT 1");
    $stmt->execute([$userId, $date, $date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getRosterDays($db, $rosterId) {
    $stmt = $db->prepare("SELECT rd.*, s.name as shift_name, s.start_time, s.end_time FROM roster_days rd LEFT JOIN shifts s ON rd.shift_id = s.id WHERE rd.roster_id = ? ORDER BY rd.day_of_week");
    $stmt->execute([$rosterId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createRoster($db, $userId, $effectiveFrom, $effectiveTo, $createdBy, $days) {
    $stmt = $db->prepare("INSERT INTO rosters (user_id, effective_from, effective_to, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $effectiveFrom, $effectiveTo ?: null, $createdBy]);
    $rosterId = $db->lastInsertId();
    $dayStmt = $db->prepare("INSERT INTO roster_days (roster_id, day_of_week, shift_id, is_rest_day) VALUES (?, ?, ?, ?)");
    foreach ($days as $d) {
        $dayStmt->execute([$rosterId, $d['day_of_week'], $d['shift_id'] ?? null, $d['is_rest_day'] ?? 0]);
    }
    return $rosterId;
}

function updateRosterDays($db, $rosterId, $days) {
    $db->prepare("DELETE FROM roster_days WHERE roster_id=?")->execute([$rosterId]);
    $stmt = $db->prepare("INSERT INTO roster_days (roster_id, day_of_week, shift_id, is_rest_day) VALUES (?, ?, ?, ?)");
    foreach ($days as $d) {
        $stmt->execute([$rosterId, $d['day_of_week'], $d['shift_id'] ?? null, $d['is_rest_day'] ?? 0]);
    }
}

function deleteRoster($db, $rosterId) {
    $db->prepare("DELETE FROM rosters WHERE id=?")->execute([$rosterId]);
}

function setRosterOverride($db, $rosterId, $overrideDate, $shiftId, $isRestDay) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO roster_overrides (roster_id, override_date, shift_id, is_rest_day) VALUES (?, ?, ?, ?)");
    $stmt->execute([$rosterId, $overrideDate, $shiftId, $isRestDay]);
}

function getShiftForDate($db, $userId, $date) {
    $d = new DateTime($date);
    $dayOfWeek = ($d->format('N') % 7);
    $roster = getUserRoster($db, $userId, $date);
    if (!$roster) return null;
    $stmt = $db->prepare("SELECT * FROM roster_overrides WHERE roster_id=? AND override_date=? LIMIT 1");
    $stmt->execute([$roster['id'], $date]);
    $override = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($override) {
        if ($override['is_rest_day']) return ['shift' => null, 'is_rest_day' => true];
        $shift = $db->query("SELECT * FROM shifts WHERE id=" . (int)$override['shift_id'])->fetch(PDO::FETCH_ASSOC);
        return ['shift' => $shift, 'is_rest_day' => false];
    }
    $stmt = $db->prepare("SELECT rd.*, s.name as shift_name, s.start_time, s.end_time FROM roster_days rd LEFT JOIN shifts s ON rd.shift_id = s.id WHERE rd.roster_id=? AND rd.day_of_week=? LIMIT 1");
    $stmt->execute([$roster['id'], $dayOfWeek]);
    $rd = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rd || $rd['is_rest_day']) return ['shift' => null, 'is_rest_day' => true];
    return ['shift' => $rd, 'is_rest_day' => false];
}

function isUserRestDay($db, $userId, $date) {
    $info = getShiftForDate($db, $userId, $date);
    return $info && $info['is_rest_day'];
}

function getUserLeaves($db, $userId, $year) {
    $stmt = $db->prepare("SELECT l.*, u.name as approved_by_name FROM leaves l LEFT JOIN users u ON l.approved_by = u.id WHERE l.user_id = ? AND (strftime('%Y', l.start_date) = ? OR strftime('%Y', l.end_date) = ?) ORDER BY l.created_at DESC");
    $stmt->execute([$userId, $year, $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function filterRequestRows($rows) {
    $search = strtolower(trim((string)($_GET['search'] ?? '')));
    $employee = trim((string)($_GET['employee'] ?? ''));
    $department = trim((string)($_GET['department'] ?? ''));
    $company = trim((string)($_GET['company'] ?? ''));
    $type = trim((string)($_GET['type'] ?? ''));
    return array_values(array_filter($rows, function ($row) use ($search, $employee, $department, $company, $type) {
        if ($employee !== '' && (string)($row['badge_id'] ?? '') !== $employee) return false;
        if ($department !== '' && (string)($row['department_name'] ?? '') !== $department) return false;
        if ($company !== '' && (string)($row['company_name'] ?? '') !== $company) return false;
        if ($type !== '' && $type !== 'all' && (string)($row['leave_type'] ?? '') !== $type) return false;
        if ($search !== '') {
            $text = strtolower(implode(' ', [$row['employee_name'] ?? '', $row['badge_id'] ?? '', $row['company_name'] ?? '', $row['department_name'] ?? '', $row['reason'] ?? '']));
            if (strpos($text, $search) === false) return false;
        }
        return true;
    }));
}

function getDepartmentPendingLeaves($db, $deptId, $status = 'pending') {
    $statusSql = $status === 'all' ? '' : ' AND l.status = ?';
    $params = [$deptId];
    if ($status !== 'all') $params[] = $status;
    $stmt = $db->prepare("SELECT l.*, COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name, u.badge_id, u.company_name, u.department_name FROM leaves l INNER JOIN users u ON l.user_id = u.id INNER JOIN user_departments ud ON u.id = ud.user_id WHERE ud.department_id = ?{$statusSql} ORDER BY l.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllPendingLeaves($db, $status = 'pending') {
    $statusSql = $status === 'all' ? '' : ' WHERE l.status = ' . $db->quote($status);
    return $db->query("SELECT l.*, COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name, u.badge_id, u.company_name, u.department_name FROM leaves l INNER JOIN users u ON l.user_id = u.id{$statusSql} ORDER BY l.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function getScopedPendingLeaves($db, $status, $userId, $isAdmin) {
    $statusSql = $status === 'all' ? '' : ' AND l.status = ?';
    $params = [];
    if ($status !== 'all') $params[] = $status;
    $scopeSql = '';
    if (!$isAdmin) {
        $scopeSql = ' AND EXISTS (SELECT 1 FROM user_departments hud JOIN departments hd ON hd.id = hud.department_id WHERE hud.user_id = l.user_id AND hd.hod_user_id = ?)';
        $params[] = $userId;
    }
    $stmt = $db->prepare("SELECT l.*, COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name, u.badge_id, u.company_name, u.department_name FROM leaves l INNER JOIN users u ON l.user_id = u.id WHERE 1=1{$statusSql}{$scopeSql} ORDER BY l.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLeaveSummary($db, $year, $userId, $isAdmin) {
    $scopeSql = '';
    $params = [$year, $year];
    if (!$isAdmin) {
        $scopeSql = ' AND EXISTS (SELECT 1 FROM user_departments hud JOIN departments hd ON hd.id = hud.department_id WHERE hud.user_id = u.id AND hd.hod_user_id = ?)';
        $params[] = $userId;
    }
    $stmt = $db->prepare("SELECT u.id, u.badge_id, COALESCE(NULLIF(u.calling_name, ''), u.name) as name, u.department_name,
        COALESCE(SUM(CASE WHEN l.status='approved' THEN julianday(l.end_date)-julianday(l.start_date)+1 ELSE 0 END),0) approved_days,
        COALESCE(SUM(CASE WHEN l.status='pending' THEN julianday(l.end_date)-julianday(l.start_date)+1 ELSE 0 END),0) pending_days,
        COALESCE(SUM(CASE WHEN l.status='rejected' THEN julianday(l.end_date)-julianday(l.start_date)+1 ELSE 0 END),0) rejected_days,
        COUNT(l.id) total_requests
        FROM users u LEFT JOIN leaves l ON l.user_id=u.id AND (strftime('%Y', l.start_date)=? OR strftime('%Y', l.end_date)=?)
        WHERE u.employee_list_member=1{$scopeSql}
        GROUP BY u.id, u.badge_id, u.name, u.calling_name, u.department_name ORDER BY u.name");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createLeave($db, $userId, $leaveType, $startDate, $endDate, $reason) {
    $start = DateTime::createFromFormat('!Y-m-d', $startDate);
    $end = DateTime::createFromFormat('!Y-m-d', $endDate);
    if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate || $end < $start) throw new InvalidArgumentException('Enter a valid leave date range.');
    if (!in_array($leaveType, ['annual', 'sick', 'casual', 'maternity', 'paternity', 'nopay'], true)) throw new InvalidArgumentException('Invalid leave type.');
    $stmt = $db->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $leaveType, $startDate, $endDate, $reason]);
    return $db->lastInsertId();
}

function approveLeave($db, $leaveId, $approvedBy) {
    $leave = $db->query("SELECT * FROM leaves WHERE id=" . (int)$leaveId)->fetch(PDO::FETCH_ASSOC);
    if (!$leave || $leave['status'] !== 'pending') return false;
    $db->prepare("UPDATE leaves SET status='approved', approved_by=? WHERE id=?")->execute([$approvedBy, $leaveId]);
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $days = $end->diff($start)->days + 1;
    $year = (int)$start->format('Y');
    $stmt = $db->prepare("UPDATE leave_balances SET used = used + ? WHERE user_id=? AND leave_type=? AND year=?");
    $stmt->execute([$days, $leave['user_id'], $leave['leave_type'], $year]);
    return true;
}

function rejectLeave($db, $leaveId, $approvedBy, $reason) {
    $db->prepare("UPDATE leaves SET status='rejected', approved_by=?, rejection_reason=? WHERE id=?")->execute([$approvedBy, $reason, $leaveId]);
}

function cancelLeave($db, $leaveId, $userId) {
    $db->prepare("UPDATE leaves SET status='cancelled' WHERE id=? AND user_id=?")->execute([$leaveId, $userId]);
}

function getLeaveBalance($db, $userId, $leaveType, $year) {
    $stmt = $db->prepare("SELECT * FROM leave_balances WHERE user_id=? AND leave_type=? AND year=?");
    $stmt->execute([$userId, $leaveType, $year]);
    $bal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bal) {
        $entitled = getEntitlement($leaveType);
        $db->prepare("INSERT INTO leave_balances (user_id, leave_type, year, entitled, used) VALUES (?, ?, ?, ?, 0)")->execute([$userId, $leaveType, $year, $entitled]);
        return ['entitled' => $entitled, 'used' => 0];
    }
    return $bal;
}

function getAllLeaveBalances($db, $userId, $year) {
    $types = ['annual', 'sick', 'casual', 'maternity', 'paternity', 'nopay'];
    $result = [];
    foreach ($types as $t) {
        $result[$t] = getLeaveBalance($db, $userId, $t, $year);
    }
    return $result;
}

function getEntitlement($leaveType) {
    $map = ['annual' => 14, 'sick' => 7, 'casual' => 3, 'maternity' => 84, 'paternity' => 7, 'nopay' => 999];
    return $map[$leaveType] ?? 0;
}

function hasOverlappingLeave($db, $userId, $startDate, $endDate, $excludeId = null) {
    $sql = "SELECT COUNT(*) FROM leaves WHERE user_id=? AND status IN ('pending','approved') AND start_date <= ? AND end_date >= ?";
    $params = [$userId, $endDate, $startDate];
    if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

function getUserOT($db, $userId, $year, $month) {
    $ym = sprintf('%04d-%02d', $year, $month);
    $stmt = $db->prepare("SELECT o.*, u.name as approved_by_name FROM overtime o LEFT JOIN users u ON o.approved_by = u.id WHERE o.user_id = ? AND strftime('%Y-%m', o.ot_date) = ? ORDER BY o.ot_date DESC");
    $stmt->execute([$userId, $ym]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDepartmentPendingOT($db, $deptId, $status = 'pending') {
    $statusSql = $status === 'all' ? '' : ' AND o.status = ?';
    $params = [$deptId];
    if ($status !== 'all') $params[] = $status;
    $stmt = $db->prepare("SELECT o.*, COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name, u.badge_id, u.company_name, u.department_name FROM overtime o INNER JOIN users u ON o.user_id = u.id INNER JOIN user_departments ud ON u.id = ud.user_id WHERE ud.department_id = ?{$statusSql} ORDER BY o.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllPendingOT($db, $status = 'pending') {
    $statusSql = $status === 'all' ? '' : ' WHERE o.status = ' . $db->quote($status);
    return $db->query("SELECT o.*, COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name, u.badge_id, u.company_name, u.department_name FROM overtime o INNER JOIN users u ON o.user_id = u.id{$statusSql} ORDER BY o.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function getScopedPendingOT($db, $status, $userId, $isAdmin) {
    $statusSql = $status === 'all' ? '' : ' AND o.status = ?';
    $params = [];
    if ($status !== 'all') $params[] = $status;
    $scopeSql = '';
    if (!$isAdmin) {
        $scopeSql = ' AND EXISTS (SELECT 1 FROM user_departments hud JOIN departments hd ON hd.id = hud.department_id WHERE hud.user_id = o.user_id AND hd.hod_user_id = ?)';
        $params[] = $userId;
    }
    $stmt = $db->prepare("SELECT o.*, COALESCE(NULLIF(u.calling_name, ''), u.name) as employee_name, u.badge_id, u.company_name, u.department_name FROM overtime o INNER JOIN users u ON o.user_id = u.id WHERE 1=1{$statusSql}{$scopeSql} ORDER BY o.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOTSummary($db, $year, $month, $userId, $isAdmin) {
    $scopeSql = '';
    $params = [$year, $month];
    if (!$isAdmin) {
        $scopeSql = ' AND EXISTS (SELECT 1 FROM user_departments hud JOIN departments hd ON hd.id = hud.department_id WHERE hud.user_id = u.id AND hd.hod_user_id = ?)';
        $params[] = $userId;
    }
    $stmt = $db->prepare("SELECT u.id, u.badge_id, COALESCE(NULLIF(u.calling_name, ''), u.name) as name, u.department_name,
        COALESCE(SUM(CASE WHEN o.status='approved' THEN o.hours ELSE 0 END),0) approved_hours,
        COALESCE(SUM(CASE WHEN o.status='pending' THEN o.hours ELSE 0 END),0) pending_hours,
        COALESCE(SUM(CASE WHEN o.status='rejected' THEN o.hours ELSE 0 END),0) rejected_hours,
        COUNT(o.id) total_requests
        FROM users u LEFT JOIN overtime o ON o.user_id=u.id AND strftime('%Y', o.ot_date)=? AND strftime('%m', o.ot_date)=?
        WHERE u.employee_list_member=1{$scopeSql}
        GROUP BY u.id, u.badge_id, u.name, u.calling_name, u.department_name ORDER BY u.name", $params);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createOT($db, $userId, $otDate, $hours, $reason) {
    $date = DateTime::createFromFormat('!Y-m-d', $otDate);
    if (!$date || $date->format('Y-m-d') !== $otDate || $hours <= 0 || $hours > 24) throw new InvalidArgumentException('Enter a valid OT date and hours between 0 and 24.');
    $stmt = $db->prepare("INSERT INTO overtime (user_id, ot_date, hours, reason) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $otDate, $hours, $reason]);
    return $db->lastInsertId();
}

function approveOT($db, $otId, $approvedBy) {
    $stmt = $db->prepare("UPDATE overtime SET status='approved', approved_by=? WHERE id=? AND status='pending'");
    $stmt->execute([$approvedBy, $otId]);
    return $stmt->rowCount() > 0;
}

function rejectOT($db, $otId, $approvedBy, $reason) {
    $db->prepare("UPDATE overtime SET status='rejected', approved_by=?, rejection_reason=? WHERE id=?")->execute([$approvedBy, $reason, $otId]);
}

function getHolidaysForYear($db, $year) {
    $stmt = $db->prepare("SELECT * FROM public_holidays WHERE year=? ORDER BY holiday_date");
    $stmt->execute([$year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getHolidaysForMonth($db, $year, $month) {
    $ym = sprintf('%04d-%02d', $year, $month);
    $stmt = $db->prepare("SELECT * FROM public_holidays WHERE strftime('%Y-%m', holiday_date) = ? ORDER BY holiday_date");
    $stmt->execute([$ym]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function isHoliday($db, $date) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM public_holidays WHERE holiday_date = ?");
    $stmt->execute([$date]);
    return $stmt->fetchColumn() > 0;
}

function addHoliday($db, $date, $name, $isPoya = 0) {
    $year = (int)(new DateTime($date))->format('Y');
    $stmt = $db->prepare("INSERT OR IGNORE INTO public_holidays (holiday_date, name, year, is_poya) VALUES (?, ?, ?, ?)");
    $stmt->execute([$date, $name, $year, $isPoya]);
}

function removeHoliday($db, $id) {
    $db->prepare("DELETE FROM public_holidays WHERE id=?")->execute([$id]);
}

function seedHolidays($db, $year) {
    $year = (int)$year;
    $holidays = [
        [$year.'-01-03', 'Duruthu Full Moon Poya Day', $year, 1],
        [$year.'-01-15', 'Tamil Thai Pongal Day', $year, 0],
        [$year.'-02-01', 'Navam Full Moon Poya Day', $year, 1],
        [$year.'-02-04', 'Independence Day', $year, 0],
        [$year.'-02-15', 'Maha Sivarathri Day', $year, 0],
        [$year.'-03-02', 'Madin Full Moon Poya Day', $year, 1],
        [$year.'-03-21', 'Id-Ul-Fitr', $year, 0],
        [$year.'-04-01', 'Bak Full Moon Poya Day', $year, 1],
        [$year.'-04-03', 'Good Friday', $year, 0],
        [$year.'-04-13', 'Day Prior to Sinhala and Tamil New Year', $year, 0],
        [$year.'-04-14', 'Sinhala and Tamil New Year Day', $year, 0],
        [$year.'-05-01', 'Vesak Full Moon Poya Day', $year, 1],
        [$year.'-05-01', 'International Labour Day', $year, 0],
        [$year.'-05-02', 'Day Following Vesak Poya', $year, 1],
        [$year.'-05-28', 'Id-Ul-Alha', $year, 0],
        [$year.'-05-30', 'Adhi Poson Full Moon Poya Day', $year, 1],
        [$year.'-06-29', 'Poson Full Moon Poya Day', $year, 1],
        [$year.'-07-29', 'Esala Full Moon Poya Day', $year, 1],
        [$year.'-08-26', 'Milad-Un-Nabi', $year, 0],
        [$year.'-08-27', 'Nikini Full Moon Poya Day', $year, 1],
        [$year.'-09-26', 'Binara Full Moon Poya Day', $year, 1],
        [$year.'-10-25', 'Vap Full Moon Poya Day', $year, 1],
        [$year.'-11-08', 'Deepavali Festival Day', $year, 0],
        [$year.'-11-24', 'Ill Full Moon Poya Day', $year, 1],
        [$year.'-12-23', 'Unduvap Full Moon Poya Day', $year, 1],
        [$year.'-12-25', 'Christmas Day', $year, 0],
    ];
    $stmt = $db->prepare("INSERT OR IGNORE INTO public_holidays (holiday_date, name, year, is_poya) VALUES (?, ?, ?, ?)");
    $count = 0;
    foreach ($holidays as $h) {
        $stmt->execute($h);
        $count += $stmt->rowCount();
    }
    return $count;
}

function logNotification($db, $userId, $type, $channel, $subject, $message, $status = 'sent') {
    if ((int)$userId < 1) return;
    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, channel, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $channel, $subject, $message, $status]);
}

function getUserNotifications($db, $userId, $limit = 50) {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllNotifications($db, $limit = 100) {
    return $db->query("SELECT n.*, u.name as user_name FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
}

function isHOD($db, $userId) {
    $user = $db->query("SELECT role FROM users WHERE id=" . (int)$userId)->fetch(PDO::FETCH_ASSOC);
    return $user && in_array($user['role'], [1, 2]);
}

function isAdmin($db, $userId) {
    $user = $db->query("SELECT role FROM users WHERE id=" . (int)$userId)->fetch(PDO::FETCH_ASSOC);
    return $user && $user['role'] == 2;
}

function getHODForDepartment($db, $deptId) {
    $stmt = $db->prepare("SELECT u.* FROM users u INNER JOIN departments d ON u.id = d.hod_user_id WHERE d.id = ?");
    $stmt->execute([$deptId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllRoles($db) {
    return $db->query("SELECT id, badge_id, name, role FROM users ORDER BY CAST(badge_id AS INTEGER) ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function updateUserRole($db, $userId, $role) {
    $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $userId]);
}
