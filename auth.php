<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/database.php';

function ensureAuthSchema($db) {
    $admin = $db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1")->fetchColumn();
    if ($admin === false) {
        $nextId = (int)$db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        $stmt = $db->prepare('INSERT INTO users (id, badge_id, name, role, username, password_hash) VALUES (?, ?, ?, 2, ?, ?)');
        $stmt->execute([$nextId, 'ADMIN', 'System Administrator', 'admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
}

function currentUser() {
    static $user = null;
    if ($user !== null) return $user;
    if (empty($_SESSION['user_id'])) return null;
    $stmt = getDB()->prepare('SELECT id, badge_id, name, username, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    return $user = ($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
}

function loginUser($username, $password) {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    return true;
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function isAdminUser() { $user = currentUser(); return $user && (int)$user['role'] === 2; }
function isHodUser() { $user = currentUser(); return $user && (int)$user['role'] === 1; }
function canAccessPage($page) { return isAdminUser() || (isHodUser() && in_array($page, ['leave', 'overtime', 'roster'], true)); }
function canManageEmployee($db, $employeeId) {
    if (isAdminUser()) return true;
    $stmt = $db->prepare('SELECT 1 FROM user_departments ud INNER JOIN departments d ON d.id = ud.department_id WHERE ud.user_id = ? AND d.hod_user_id = ? LIMIT 1');
    $stmt->execute([(int)$employeeId, (int)currentUser()['id']]);
    return (bool)$stmt->fetchColumn();
}

function requireLoginPage($page = '') {
    $db = getDB(); ensureAuthSchema($db);
    if (!currentUser() || ($page !== '' && !canAccessPage($page))) {
        if ($page !== '' && currentUser() && !canAccessPage($page)) { http_response_code(403); exit('Access denied.'); }
        header('Location: login.php'); exit;
    }
}

function requireLoginApi($roles = []) {
    $db = getDB(); ensureAuthSchema($db); $user = currentUser();
    if (!$user) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Please log in first.']); exit; }
    if ($roles && !in_array((int)$user['role'], $roles, true)) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'You do not have permission for this action.']); exit; }
}

ensureAuthSchema(getDB());