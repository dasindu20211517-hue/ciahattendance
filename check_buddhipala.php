<?php
require_once __DIR__ . '/database.php';

try {
    $db = getDB();
    
    echo "<h2>Checking BUDDHIPALA Employee</h2>";
    
    // Search for the employee
    $stmt = $db->prepare("SELECT * FROM users WHERE name LIKE ? OR name LIKE ? LIMIT 1");
    $stmt->execute(['%BUDDHIPALA%', '%D. K. U. T. K%']);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        echo "<h3>✅ Employee Found in Database:</h3>";
        echo "<table border='1' style='border-collapse: collapse; font-family: monospace;'>";
        foreach ($employee as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? '') . "</td></tr>";
        }
        echo "</table>";
        
        // Check if appears in daily summary
        echo "<h3>Daily Summary Check for Today (" . date('Y-m-d') . "):</h3>";
        $dailySummary = getDailySummary($db, date('Y-m-d'), true);
        
        $found = false;
        foreach ($dailySummary as $record) {
            if (stripos($record['name'], 'BUDDHIPALA') !== false || 
                $record['badge_id'] === $employee['badge_id']) {
                echo "<p>✅ <strong>Found in daily summary!</strong></p>";
                echo "<pre>";
                print_r($record);
                echo "</pre>";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "<p>❌ <strong>NOT found in daily summary</strong></p>";
            echo "<p>This means the employee exists but getDailySummary() is not returning her.</p>";
            
            // Check employee_list_member flag
            if ($employee['employee_list_member'] != 1) {
                echo "<p>🔍 <strong>Issue found:</strong> employee_list_member = " . $employee['employee_list_member'] . " (should be 1)</p>";
                echo "<p><strong>Fix:</strong> Run this SQL to fix it:</p>";
                echo "<code>UPDATE users SET employee_list_member = 1 WHERE id = " . $employee['id'] . ";</code>";
            }
        }
        
    } else {
        echo "<h3>❌ Employee NOT Found</h3>";
        echo "<p>Searching for similar names...</p>";
        
        $stmt = $db->prepare("SELECT badge_id, employee_id, name FROM users WHERE name LIKE ? OR name LIKE ? LIMIT 5");
        $stmt->execute(['%K.%', '%MS.%']);
        $similar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($similar) {
            echo "<table border='1'>";
            echo "<tr><th>Badge ID</th><th>Employee ID</th><th>Name</th></tr>";
            foreach ($similar as $emp) {
                echo "<tr><td>" . htmlspecialchars($emp['badge_id']) . "</td><td>" . htmlspecialchars($emp['employee_id'] ?? '') . "</td><td>" . htmlspecialchars($emp['name']) . "</td></tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>