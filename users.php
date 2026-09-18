<?php
$pageTitle = 'User Access';
$activePage = 'users';
require_once __DIR__ . '/includes/header.php';
$db = getDB();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'create_hod') {
            $hodId = (int)($_POST['hod_user_id'] ?? 0);
            $departmentId = (int)($_POST['department_id'] ?? 0);
            $username = trim((string)($_POST['hod_username'] ?? ''));
            $password = (string)($_POST['hod_password'] ?? '');
            $employeeIds = array_values(array_unique(array_map('intval', $_POST['employee_ids'] ?? [])));
            if ($hodId <= 0 || $departmentId <= 0 || $username === '' || strlen($password) < 8) throw new RuntimeException('Select an employee and department; password must be at least 8 characters.');
            $check = $db->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $check->execute([$username, $hodId]);
            if ($check->fetchColumn()) throw new RuntimeException('That username is already in use.');
            $db->beginTransaction();
            $stmt = $db->prepare('UPDATE users SET role=1, username=?, password_hash=? WHERE id=?');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $hodId]);
            $db->prepare('UPDATE departments SET hod_user_id=NULL WHERE hod_user_id=?')->execute([$hodId]);
            $db->prepare('UPDATE departments SET hod_user_id=? WHERE id=?')->execute([$hodId, $departmentId]);
            $db->prepare('DELETE FROM user_departments WHERE department_id=?')->execute([$departmentId]);
            $link = $db->prepare('INSERT OR IGNORE INTO user_departments (user_id, department_id) VALUES (?, ?)');
            foreach ($employeeIds as $employeeId) {
                if ($employeeId > 0 && $employeeId !== $hodId) $link->execute([$employeeId, $departmentId]);
            }
            $db->commit();
            $message = 'HOD created and employees assigned.';
        } else {
        $userId = (int)($_POST['user_id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = 1;
        if ($userId <= 0 || $username === '') throw new RuntimeException('Select an HOD and enter a username.');
        $roleCheck = $db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $roleCheck->execute([$userId]);
        if ((int)$roleCheck->fetchColumn() !== 1) throw new RuntimeException('Only HOD accounts can be changed here.');
        $check = $db->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $check->execute([$username, $userId]);
        if ($check->fetchColumn()) throw new RuntimeException('That username is already in use.');
        if ($password !== '') {
            $stmt = $db->prepare('UPDATE users SET username = ?, password_hash = ?, role = ? WHERE id = ?');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $userId]);
        } else {
            $stmt = $db->prepare('UPDATE users SET username = ?, role = ? WHERE id = ?');
            $stmt->execute([$username, $role, $userId]);
        }
        $message = 'User access updated.';
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}
$users = $db->query('SELECT id, badge_id, name, username, role FROM users WHERE role = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $availableEmployees = $db->query('SELECT id, badge_id, employee_id, name, COALESCE(NULLIF(calling_name, \'\'), name) as display_name FROM users WHERE role = 0 AND employee_list_member = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $departments = $db->query('SELECT id, name FROM departments ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
function roleLabel($role) { return (int)$role === 2 ? 'Admin' : ((int)$role === 1 ? 'HOD' : 'No login'); }
?>
<style>#createHod .hod-credential{position:relative}#createHod .hod-credential label{display:flex;align-items:center;gap:7px}#createHod .hod-credential label::before{content:'●';color:var(--primary);font-size:9px}#createHod .hod-credential input{width:100%;height:42px;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--radius);background:#fff;color:var(--text-primary);font:500 13px Inter,sans-serif;outline:0;transition:var(--transition)}#createHod .hod-credential input::placeholder{color:var(--text-muted)}#createHod .hod-credential input:hover{border-color:var(--primary);background:var(--primary-50)}#createHod .hod-credential input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,.14)}#createHod .credential-help{display:block;margin-top:6px;color:var(--text-muted);font-size:11px}.employee-checkbox-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px;max-height:320px;overflow:auto;padding:12px;border:1px solid var(--border);border-radius:var(--radius);background:#fbfdfd}.employee-checkbox{display:flex;align-items:center;gap:10px;padding:10px;border:1px solid transparent;border-radius:6px;cursor:pointer;font-size:13px}.employee-checkbox:hover{background:var(--primary-50);border-color:var(--primary-100)}.employee-checkbox input{width:17px;height:17px;accent-color:var(--primary);flex:0 0 auto}.employee-checkbox span{line-height:1.3}</style>
<div class="controls-container" id="createHod"><h3>Create HOD access</h3><p><strong>First time?</strong> Select an employee, create their HOD login, choose their department, and assign the employees they can manage.</p><form method="post" onsubmit="return validateHodEmployees(this)"><input type="hidden" name="action" value="create_hod"><div class="controls-group"><div class="control-item"><label>Employee to become HOD</label><select name="hod_user_id" required><option value="">Select employee</option><?php foreach ($availableEmployees as $employee): ?><option value="<?php echo (int)$employee['id']; ?>"><?php echo htmlspecialchars($employee['display_name'] . ' (' . $employee['badge_id'] . ')'); ?></option><?php endforeach; ?></select></div><div class="control-item"><label>Department</label><select name="department_id" required><option value="">Select department</option><?php foreach ($departments as $department): ?><option value="<?php echo (int)$department['id']; ?>"><?php echo htmlspecialchars($department['name']); ?></option><?php endforeach; ?></select></div><div class="control-item hod-credential"><label for="hodUsername">HOD username</label><input id="hodUsername" name="hod_username" placeholder="e.g. hod.receiving" required autocomplete="username"><small class="credential-help">Used to sign in</small></div><div class="control-item hod-credential"><label for="hodPassword">HOD password</label><input id="hodPassword" name="hod_password" type="password" minlength="8" placeholder="Minimum 8 characters" required autocomplete="new-password"><small class="credential-help">Keep this private</small></div></div><div class="control-item" style="margin-top:16px"><label>Assign employees to this HOD <small style="font-weight:400;text-transform:none;color:var(--text-muted)">(select one or more)</small></label><div class="employee-checkbox-grid"><?php foreach ($availableEmployees as $employee): ?><label class="employee-checkbox"><input type="checkbox" name="employee_ids[]" value="<?php echo (int)$employee['id']; ?>"><span><?php echo htmlspecialchars($employee['display_name'] . ' (' . $employee['badge_id'] . ')'); ?></span></label><?php endforeach; ?></div></div><button class="btn btn-primary" type="submit" style="margin-top:16px">Create HOD and assign employees</button></form></div>
<script>function validateHodEmployees(form){if(!form.querySelector('input[name="employee_ids[]"]:checked')){alert('Select at least one employee for this HOD.');return false}return true}</script>
<div class="controls-container"><h3>Manage HOD access</h3><p>Only HOD accounts are shown here. Employees do not have application login access. Admin access is managed separately.</p></div>
<?php if ($message): ?><div class="sync-status show success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="sync-status show error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="table-wrapper"><table><thead><tr><th>HOD</th><th>Enroll Number</th><th>Employee ID</th><th>Current username</th><th>Access</th><th>Change password</th></tr></thead><tbody>
<?php if (!$users): ?><tr><td colspan="6" class="no-data">No HOD accounts configured yet. Use the Create HOD access form above.</td></tr><?php endif; ?>
<?php foreach ($users as $user): ?><tr><td><?php echo htmlspecialchars($user['name']); ?></td><td><?php echo htmlspecialchars($user['badge_id']); ?></td><td><?php echo htmlspecialchars($user['employee_id'] ?? '-'); ?></td><td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td><td><?php echo roleLabel($user['role']); ?></td><td><form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>"><input name="username" placeholder="Username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required><input name="password" type="password" placeholder="New password"><button class="btn btn-secondary" type="submit">Save</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>