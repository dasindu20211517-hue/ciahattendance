<?php
$pageTitle = 'Weekly Roster Import';
$activePage = 'roster';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$db = getDB();

// ---- TEMPLATE DOWNLOAD — before any HTML output ----
if (isset($_GET['download_template'])) {
    $shifts = $db->query("SELECT name, start_time, end_time FROM shifts WHERE is_active = 1 ORDER BY is_rostered DESC, name")->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="weekly_roster_template.csv"');
    header('Cache-Control: max-age=0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    // Instructions block
    fputcsv($out, ['WEEKLY ROSTER TEMPLATE']);
    fputcsv($out, ['Fill in shift names for each day. Use OFF or leave blank for rest days.']);
    fputcsv($out, ['Available shifts: ' . implode(', ', array_column($shifts, 'name')) . ', OFF']);
    fputcsv($out, ['start_date format: YYYY-MM-DD (e.g. ' . date('Y-m-d') . ')']);
    fputcsv($out, []);

    // Header row
    fputcsv($out, ['employee_id', 'employee_name', 'start_date', 'end_date', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);

    // One row per employee with sample data
    $users = $db->query("SELECT badge_id, employee_id, COALESCE(NULLIF(calling_name,''),name) as name FROM users WHERE employee_list_member=1 ORDER BY COALESCE(NULLIF(employee_id,''),badge_id) COLLATE NOCASE ASC")->fetchAll(PDO::FETCH_ASSOC);
    $defaultShift = $shifts[0]['name'] ?? 'General';
    foreach ($users as $u) {
        fputcsv($out, [
            $u['employee_id'] ?: $u['badge_id'],
            $u['name'],
            date('Y-m-01'), // start of current month
            '',             // end_date optional
            $defaultShift, $defaultShift, $defaultShift, $defaultShift, $defaultShift, 'OFF', 'OFF'
        ]);
    }
    fclose($out);
    exit;
}

// ---- Now safe to output HTML ----
require_once __DIR__ . '/includes/header.php';

$message = '';
$error = '';
$importLog = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['roster_file'])) {
    try {
        $file = $_FILES['roster_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('File upload failed.');

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx','xls','csv'])) throw new Exception('Please upload .xlsx, .xls, or .csv');

        $uploadPath = sys_get_temp_dir() . '/roster_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) throw new Exception('Failed to save uploaded file.');

        // Parse file into rows of [header => value]
        if ($ext === 'csv') {
            $rows = [];
            $headers = [];
            $handle = fopen($uploadPath, 'r');
            $i = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if ($i === 0) { $headers = array_map('strtolower', array_map('trim', $row)); }
                else { $rows[] = array_combine($headers, array_pad($row, count($headers), '')); }
                $i++;
            }
            fclose($handle);
        } else {
            $spreadsheet = IOFactory::load($uploadPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);
            $headers = array_map('strtolower', array_map('trim', $data[0]));
            $rows = [];
            for ($i = 1; $i < count($data); $i++) {
                $rows[] = array_combine($headers, array_pad($data[$i], count($headers), ''));
            }
        }
        unlink($uploadPath);

        // Load shifts
        $shifts = [];
        foreach ($db->query("SELECT id, name FROM shifts WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $shifts[strtolower(trim($s['name']))] = $s['id'];
        }

        $created = 0; $skipped = 0; $errCount = 0;
        $db->beginTransaction();

        $dayNames = ['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];

        foreach ($rows as $row) {
            $empId = trim($row['employee_id'] ?? $row['badge_id'] ?? $row['emp_id'] ?? '');
            if (empty($empId)) { $skipped++; continue; }

            $userStmt = $db->prepare("SELECT id FROM users WHERE badge_id = ? OR employee_id = ? LIMIT 1");
            $userStmt->execute([$empId, $empId]);
            $userId = $userStmt->fetchColumn();
            if (!$userId) { $importLog[] = "Skipped: $empId not found"; $errCount++; continue; }

            $startDate = trim($row['start_date'] ?? $row['effective_from'] ?? date('Y-m-d'));
            $endDate   = trim($row['end_date']   ?? $row['effective_to']   ?? '') ?: null;

            // Build days array
            $days = [];
            foreach ($dayNames as $dayName => $dow) {
                $shiftVal = strtolower(trim($row[$dayName] ?? $row[$dayName.'_shift'] ?? ''));
                if (empty($shiftVal) || in_array($shiftVal, ['off','rest','-'])) {
                    $days[] = ['day_of_week' => $dow, 'shift_id' => null, 'is_rest_day' => 1];
                } else {
                    $shiftId = $shifts[$shiftVal] ?? null;
                    $days[] = ['day_of_week' => $dow, 'shift_id' => $shiftId, 'is_rest_day' => $shiftId ? 0 : 1];
                    if (!$shiftId) $importLog[] = "Warning: shift '$shiftVal' not found for $empId on $dayName — set as rest day";
                }
            }

            createRoster($db, $userId, $startDate, $endDate, currentUser()['id'], $days);
            $importLog[] = "Created roster for $empId from $startDate";
            $created++;
        }

        $db->commit();
        $message = "Import complete: $created rosters created, $errCount errors, $skipped skipped.";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (isset($uploadPath) && file_exists($uploadPath)) unlink($uploadPath);
        $error = $e->getMessage();
    }
}

// Load available shifts for display
$availableShifts = $db->query("SELECT name, start_time, end_time FROM shifts WHERE is_active = 1 ORDER BY is_rostered DESC, name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="controls-container">
    <h3>Weekly Roster Import</h3>
    <p style="color:var(--text-muted);margin-top:4px;">Upload an Excel or CSV file to bulk-assign weekly shift patterns per employee.</p>
</div>

<?php if ($message): ?>
    <div class="sync-status show success" style="margin:0 0 16px 0"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="sync-status show error" style="margin:0 0 16px 0"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="controls-container" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Upload Form -->
    <div>
        <h4 style="margin-bottom:12px;">Step 1 — Download Template</h4>
        <a href="roster_import.php?download_template=1" class="btn btn-secondary" style="margin-bottom:16px;display:inline-block;">Download Template CSV</a>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">Pre-filled with all employees. Fill in shift names for each day.</p>

        <h4 style="margin-bottom:12px;">Step 2 — Upload Roster File</h4>
        <form method="post" enctype="multipart/form-data">
            <div class="control-item" style="margin-bottom:12px;">
                <label for="roster_file">Select File (.xlsx, .xls, .csv)</label>
                <input type="file" id="roster_file" name="roster_file" accept=".xlsx,.xls,.csv" required style="margin-top:6px;">
            </div>
            <button type="submit" class="btn btn-primary">Import Weekly Roster</button>
            <a href="roster.php" class="btn btn-outline" style="margin-left:8px;">Back to Roster</a>
        </form>
    </div>

    <!-- Available Shifts -->
    <div>
        <h4 style="margin-bottom:12px;">Available Shifts</h4>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($availableShifts as $s): ?>
                <div style="padding:6px 12px;background:var(--primary-50);border-radius:6px;border-left:3px solid var(--primary);font-size:13px;">
                    <strong><?= htmlspecialchars($s['name']) ?></strong>
                    <span style="color:var(--text-muted);margin-left:6px;"><?= $s['start_time'] ?> – <?= $s['end_time'] ?></span>
                </div>
            <?php endforeach; ?>
            <div style="padding:6px 12px;background:#fef2f2;border-radius:6px;border-left:3px solid #ef4444;font-size:13px;"><strong>OFF</strong></div>
        </div>
    </div>
</div>

<?php if (!empty($importLog)): ?>
<div class="controls-container">
    <h4 style="margin-bottom:8px;">Import Log</h4>
    <div style="max-height:200px;overflow-y:auto;background:var(--body-bg);padding:10px;border-radius:6px;border:1px solid var(--border);font-size:12px;font-family:monospace;">
        <?php foreach ($importLog as $log): ?>
            <div style="padding:2px 0;border-bottom:1px solid var(--border)"><?= htmlspecialchars($log) ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
