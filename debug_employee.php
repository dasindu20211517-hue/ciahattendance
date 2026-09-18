<?php
require_once __DIR__ . '/database.php';

echo "<h2>Employee Debug Check</h2>";

try {
    $db = getDB();
    
    // Check for the specific employee
    echo "<h3>Searching for BUDDHIPALA employee:</h3>";
    
    $searches = [
        ['badge_id', '00036'],
        ['badge_id', '36'],
        ['employee_id', '03067'],
        ['employee_id', '3067'],
        ['name LIKE', '%BUDDHIPALA%'],
        ['name LIKE', '%D. K. U. T. K.%']
    ];
    
    foreach ($searches as [$field, $value]) {
        $operator = strpos($field, 'LIKE') ? 'LIKE' : '=';
        $stmt = $db->prepare("SELECT id, badge_id, employee_id, name, gender, employee_list_member, company_name, department_name FROM users WHERE $field $operator ?");
        $stmt->execute([$value]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Search by $field = '$value':</strong> ";
        if ($user) {
            echo "FOUND!</p>";
            echo "<pre>";
            print_r($user);
            echo "</pre>";
            break;
        } else {
            echo "Not found</p>";
        }
    }
    
    // Show all employees for reference
    echo "<h3>All Employees in Database:</h3>";
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE employee_list_member = 1");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total employees: " . $total['total'] . "</p>";
    
    // Show first 10 employees
    echo "<h3>Sample Employee Records:</h3>";
    $stmt = $db->query("SELECT badge_id, employee_id, name, gender, employee_list_member FROM users WHERE employee_list_member = 1 LIMIT 10");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Badge ID</th><th>Employee ID</th><th>Name</th><th>Gender</th><th>List Member</th></tr>";
    foreach ($employees as $emp) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($emp['badge_id']) . "</td>";
        echo "<td>" . htmlspecialchars($emp['employee_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($emp['name']) . "</td>";
        echo "<td>" . htmlspecialchars($emp['gender'] ?? '') . "</td>";
        echo "<td>" . ($emp['employee_list_member'] ? 'YES' : 'NO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check gender stats
    echo "<h3>Gender Statistics:</h3>";
    $genderStats = getGenderStats($db);
    echo "<pre>";
    print_r($genderStats);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>