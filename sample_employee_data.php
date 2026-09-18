<?php
/**
 * Sample Employee Data Template with Gender (M/F)
 * This script demonstrates how to add gender data to existing employees
 * Run this script once to populate sample gender data for testing
 */

require_once __DIR__ . '/database.php';

try {
    $db = getDB();
    
    // Sample data - you can modify this array with your actual employee data
    $sampleEmployees = [
        ['badge_id' => '00001', 'name' => 'John Doe', 'gender' => 'M', 'department' => 'IT', 'company' => 'TechCorp'],
        ['badge_id' => '00002', 'name' => 'Jane Smith', 'gender' => 'F', 'department' => 'HR', 'company' => 'TechCorp'],
        ['badge_id' => '00003', 'name' => 'Michael Johnson', 'gender' => 'M', 'department' => 'Finance', 'company' => 'TechCorp'],
        ['badge_id' => '00004', 'name' => 'Sarah Wilson', 'gender' => 'F', 'department' => 'Marketing', 'company' => 'TechCorp'],
        ['badge_id' => '00005', 'name' => 'David Brown', 'gender' => 'M', 'department' => 'IT', 'company' => 'TechCorp'],
        ['badge_id' => '00006', 'name' => 'Lisa Davis', 'gender' => 'F', 'department' => 'HR', 'company' => 'TechCorp'],
        ['badge_id' => '00007', 'name' => 'Robert Miller', 'gender' => 'M', 'department' => 'Operations', 'company' => 'TechCorp'],
        ['badge_id' => '00008', 'name' => 'Emily Anderson', 'gender' => 'F', 'department' => 'Marketing', 'company' => 'TechCorp'],
    ];
    
    $db->beginTransaction();
    
    // Insert or update sample employees
    $insertStmt = $db->prepare("INSERT OR IGNORE INTO users (id, badge_id, name, gender, department_name, company_name, employee_list_member) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $updateStmt = $db->prepare("UPDATE users SET gender = ?, department_name = ?, company_name = ? WHERE badge_id = ?");
    
    foreach ($sampleEmployees as $index => $emp) {
        $userId = $index + 1;
        
        // Try to insert new employee
        $insertStmt->execute([
            $userId,
            $emp['badge_id'],
            $emp['name'],
            $emp['gender'],
            $emp['department'],
            $emp['company']
        ]);
        
        // Update existing employee with gender info
        $updateStmt->execute([
            $emp['gender'],
            $emp['department'],
            $emp['company'],
            $emp['badge_id']
        ]);
    }
    
    $db->commit();
    
    echo "Sample employee data created successfully!\n\n";
    
    // Display gender statistics
    $genderStats = getGenderStats($db);
    echo "Gender Statistics:\n";
    echo "- Male employees: " . $genderStats['male_count'] . "\n";
    echo "- Female employees: " . $genderStats['female_count'] . "\n";
    echo "- Total employees: " . $genderStats['total_count'] . "\n\n";
    
    // Display all employees with gender
    echo "Employee List:\n";
    echo str_pad("ID", 8) . str_pad("Name", 25) . str_pad("Gender", 8) . str_pad("Department", 15) . "Company\n";
    echo str_repeat("-", 70) . "\n";
    
    $employees = getAllUsers($db);
    foreach ($employees as $emp) {
        echo str_pad($emp['badge_id'], 8) . 
             str_pad($emp['name'], 25) . 
             str_pad($emp['gender'] ?: 'N/A', 8) . 
             str_pad($emp['department_name'] ?: 'N/A', 15) . 
             ($emp['company_name'] ?: 'N/A') . "\n";
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>