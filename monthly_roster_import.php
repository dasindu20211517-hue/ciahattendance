<?php
$pageTitle = 'Monthly Roster Import';
$activePage = 'roster';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$db = getDB();

// ---- TEMPLATE DOWNLOAD — must happen before any output ----
if (isset($_GET['download_template'])) {
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('m'));
    $daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="monthly_roster_' . $year . '_' . sprintf('%02d',$month) . '.csv"');
    header('Cache-Control: max-age=0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    // Instructions
    $shifts = $db->query("SELECT name FROM shifts WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    fputcsv($out, ['MONTHLY ROSTER — ' . date('F Y', mktime(0,0,0,$month,1,$year))]);
    fputcsv($out, ['Fill shift names in each day column. Leave blank to skip. Use OFF for rest days.']);
    fputcsv($out, ['Available shifts: ' . implode(', ', $shifts) . ', OFF']);
    fputcsv($out, []);

    $headerRow = ['employee_id', 'employee_name'];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dayName = date('D', mktime(0,0,0,$month,$d,$year));
        $headerRow[] = 'day_' . $d . '_' . $dayName;
    }
    fputcsv($out, $headerRow);

    $users = $db->query("SELECT badge_id, employee_id, COALESCE(NULLIF(calling_name,''),name) as name FROM users WHERE employee_list_member=1 ORDER BY COALESCE(NULLIF(employee_id,''),badge_id) COLLATE NOCASE ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $row = [$u['employee_id'] ?: $u['badge_id'], $u['name']];
        for ($d = 1; $d <= $daysInMonth; $d++) $row[] = '';
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ---- NOW safe to include header (outputs HTML) ----
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

        $targetYear  = (int)($_POST['import_year']  ?? date('Y'));
        $targetMonth = (int)($_POST['import_month'] ?? date('m'));

        $uploadPath = sys_get_temp_dir() . '/monthly_roster_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) throw new Exception('Failed to save uploaded file.');

        // Parse into rows
        if ($ext === 'csv') {
            $rawRows = [];
            $handle = fopen($uploadPath, 'r');
            $i = 0;
            while (($row = fgetcsv($handle)) !== false) {
                // Skip BOM
                if ($i === 0 && isset($row[0])) $row[0] = ltrim($row[0], "\xEF\xBB\xBF");
                $rawRows[] = $row;
                $i++;
            }
            fclose($handle);
        } else {
            $spreadsheet = IOFactory::load($uploadPath);
            $rawRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        }
        unlink($uploadPath);

        if (empty($rawRows)) throw new Exception('File is empty.');

        $headers = array_map('trim', $rawRows[0]);

        // Load shifts (case-insensitive lookup)
        $shifts = [];
        foreach ($db->query("SELECT id, name FROM shifts WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $shifts[strtolower(trim($s['name']))] = $s['id'];
        }

        $created = 0; $updated = 0; $errCount = 0;
        $db->beginTransaction();
        $monthStart = sprintf('%04d-%02d-01', $targetYear, $targetMonth);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        for ($ri = 1; $ri < count($rawRows); $ri++) {
            $row = array_pad($rawRows[$ri], count($headers), '');
            $rowData = array_combine($headers, $row);

            $empId = trim($rowData['employee_id'] ?? $rowData['badge_id'] ?? '');
            if (empty($empId)) continue;

            $userStmt = $db->prepare("SELECT id FROM users WHERE badge_id = ? OR employee_id = ? LIMIT 1");
            $userStmt->execute([$empId, $empId]);
            $userId = $userStmt->fetchColumn();
            if (!$userId) { $importLog[] = "Skipped: '$empId' not found"; $errCount++; continue; }

            // Ensure a roster exists for this employee for this month
            $rosterStmt = $db->prepare("SELECT id FROM rosters WHERE user_id=? AND effective_from=? AND (effective_to IS NULL OR effective_to=?) LIMIT 1");
            $rosterStmt->execute([$userId, $monthStart, $monthEnd]);
            $rosterId = $rosterStmt->fetchColumn();

            if (!$rosterId) {
                // Create a new monthly roster with all rest days as default
                $ins = $db->prepare("INSERT INTO rosters (user_id, effective_from, effective_to, created_by) VALUES (?,?,?,?)");
                $ins->execute([$userId, $monthStart, $monthEnd, currentUser()['id']]);
                $rosterId = $db->lastInsertId();
                $dayIns = $db->prepare("INSERT INTO roster_days (roster_id, day_of_week, shift_id, is_rest_day) VALUES (?,?,NULL,1)");
                for ($dow = 1; $dow <= 7; $dow++) $dayIns->execute([$rosterId, $dow]);
            }

            // Now process each day column: day_1_Mon, day_2_Tue, ...
            $daysInMonth = (int)date('t', strtotime($monthStart));
            for ($d = 1; $d <= $daysInMonth; $d++) {
                // Find matching column — try day_N_XXX variants
                $dayDate  = sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, $d);
                $dayLabel = date('D', strtotime($dayDate));
                $colKey   = 'day_' . $d . '_' . $dayLabel;

                // fallback: try numeric day key
                $shiftVal = strtolower(trim(
                    $rowData[$colKey] ??
                    $rowData['day_' . $d] ??
                    $rowData[(string)$d] ??
                    ''
                ));

                if ($shiftVal === '' ) continue; // leave blank days alone

                $isRest  = in_array($shiftVal, ['off','rest','-','0']);
                $shiftId = $isRest ? null : ($shifts[$shiftVal] ?? null);

                if (!$isRest && !$shiftId) {
                    $importLog[] = "Warning: shift '$shiftVal' not found for $empId on $dayDate — set as rest day";
                    $isRest = true;
                }

                $overrideStmt = $db->prepare("INSERT OR REPLACE INTO roster_overrides (roster_id, override_date, shift_id, is_rest_day) VALUES (?,?,?,?)");
                $overrideStmt->execute([$rosterId, $dayDate, $shiftId, $isRest ? 1 : 0]);
                $created++;
            }
            $importLog[] = "Processed: $empId for " . date('F Y', strtotime($monthStart));
        }

        $db->commit();
        $message = "Monthly roster import complete: $created day assignments saved, $errCount employees skipped.";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (isset($uploadPath) && file_exists($uploadPath)) unlink($uploadPath);
        $error = $e->getMessage();
    }
}

$availableShifts = $db->query("SELECT name, start_time, end_time FROM shifts WHERE is_active = 1 ORDER BY is_rostered DESC, name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="controls-container">
    <h3>Monthly Roster Import</h3>
    <p style="color:var(--text-muted);margin-top:4px;">Assign a specific shift to each employee for each day of a month.</p>
</div>

<?php if ($message): ?>
    <div class="sync-status show success" style="margin:0 0 16px 0"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="sync-status show error" style="margin:0 0 16px 0"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Template Download -->
<div class="controls-container">
    <h4 style="margin-bottom:12px;">Step 1 — Download Template</h4>
    <form method="get" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <input type="hidden" name="download_template" value="1">
        <div class="control-item" style="margin:0">
            <label>Year</label>
            <input type="number" name="year" value="<?= date('Y') ?>" min="2024" max="2030" style="width:90px">
        </div>
        <div class="control-item" style="margin:0">
            <label>Month</label>
            <select name="month">
                <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?=$m?>" <?= $m == date('n') ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary" style="margin-top:18px;">Download Template CSV</button>
    </form>
    <p style="font-size:12px;color:var(--text-muted);margin-top:8px;">Template contains all active employees as rows and each day of the month as columns. Fill in shift names or OFF.</p>
</div>

<!-- Upload Form -->
<div class="controls-container">
    <h4 style="margin-bottom:12px;">Step 2 — Upload Completed File</h4>
    <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
        <div>
            <div class="control-item" style="margin-bottom:12px;">
                <label>Target Month</label>
                <div style="display:flex;gap:8px;margin-top:6px;">
                    <select name="import_year">
                        <?php for ($y=2024;$y<=2030;$y++): ?>
                            <option value="<?=$y?>" <?= $y == date('Y') ? 'selected' : '' ?>><?=$y?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="import_month">
                        <?php for ($m=1;$m<=12;$m++): ?>
                            <option value="<?=$m?>" <?= $m == date('n') ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="control-item" style="margin-bottom:16px;">
                <label for="roster_file">Select File (.xlsx, .xls, .csv)</label>
                <input type="file" id="roster_file" name="roster_file" accept=".xlsx,.xls,.csv" required style="margin-top:6px;">
            </div>
            <button type="submit" class="btn btn-primary">Import Monthly Roster</button>
            <a href="roster.php" class="btn btn-outline" style="margin-left:8px;">Back to Roster</a>
        </div>
        <div>
            <h4 style="margin-bottom:10px;">Available Shifts</h4>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($availableShifts as $s): ?>
                    <div style="padding:6px 12px;background:var(--primary-50);border-radius:6px;border-left:3px solid var(--primary);font-size:12px;">
                        <strong><?= htmlspecialchars($s['name']) ?></strong>
                        <span style="color:var(--text-muted);margin-left:4px;"><?= $s['start_time'] ?>–<?= $s['end_time'] ?></span>
                    </div>
                <?php endforeach; ?>
                <div style="padding:6px 12px;background:#fef2f2;border-radius:6px;border-left:3px solid #ef4444;font-size:12px;"><strong>OFF</strong></div>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($importLog)): ?>
<div class="controls-container">
    <h4 style="margin-bottom:8px;">Import Log</h4>
    <div style="max-height:220px;overflow-y:auto;background:var(--body-bg);padding:10px;border-radius:6px;border:1px solid var(--border);font-size:12px;font-family:monospace;">
        <?php foreach ($importLog as $log): ?>
            <div style="padding:2px 0;border-bottom:1px solid var(--border)"><?= htmlspecialchars($log) ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
