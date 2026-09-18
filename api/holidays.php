<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
requireLoginApi([2]);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    $db = getDB();
    switch ($action) {
        case 'list':
            $year = (int)($_GET['year'] ?? date('Y'));
            echo json_encode(['success' => true, 'holidays' => getHolidaysForYear($db, $year)]);
            break;
        case 'month':
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('m'));
            echo json_encode(['success' => true, 'holidays' => getHolidaysForMonth($db, $year, $month)]);
            break;
        case 'check':
            $date = $_GET['date'] ?? date('Y-m-d');
            echo json_encode(['success' => true, 'is_holiday' => isHoliday($db, $date)]);
            break;
        case 'add':
            addHoliday($db, $_POST['date'], $_POST['name'], (int)($_POST['is_poya'] ?? 0));
            echo json_encode(['success' => true]);
            break;
        case 'remove':
            removeHoliday($db, (int)($_POST['id'] ?? 0));
            echo json_encode(['success' => true]);
            break;
        case 'seed':
            $year = (int)($_POST['year'] ?? date('Y'));
            $count = seedHolidays($db, $year);
            echo json_encode(['success' => true, 'message' => "Added $count holidays for $year", 'count' => $count]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
