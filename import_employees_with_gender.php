<?php
/**
 * Employee Import Script with Gender Support
 * This script imports employee data from CSV with M/F gender column
 * 
 * CSV Format Expected:
 * Employee ID,Badge ID,Name,M/F,Department,Company,Phone
 * 
 * Usage: php import_employees_with_gender.php employee_template.csv
 */

require_once __DIR__ . '/database.php';

if ($argc < 2) {
    echo "Usage: php import_employees_with_gender.php <csv_file>\n";
    echo "Example: php import_employees_with_gender.php employee_template.csv\n";
    exit(1);
}

$csvFile = $argv[1];

if (!file_exists($csvFile)) {
    echo "Error: File '$csvFile' not found.\n";
    exit(1);
}

try {
    $db = getDB();
    
    $file = fopen($csvFile, 'r');
    if (!$file) {
        throw new Exception("Could not open CSV file: $csvFile");
    }
    
    // Read header row
    $headers = fgetcsv($file);
    if (!$headers) {
        throw new Exception("Could not read CSV headers");
    }
    
    echo "CSV Headers: " . implode(', ', $headers) . "\n\n";
    
    // Map headers to expected columns
    $headerMap = [];
    foreach ($headers as $index => $header) {
        $header = trim(strtolower($header));
        if (in_array($header, ['employee id', 'employee_id', 'emp_id'])) {
            $headerMap['employee_id'] = $index;
        } elseif (in_array($header, ['badge id', 'badge_id', 'enroll', 'enroll_id'])) {
            $headerMap['badge_id'] = $index;
        } elseif (in_array($header, ['name', 'employee_name', 'full_name'])) {
            $headerMap['name'] = $index;
        } elseif (in_array($header, ['m/f', 'gender', 'sex', 'M/F'])) {
            $headerMap['gender'] = $index;
        } elseif (in_array($header, ['department', 'dept'])) {
            $headerMap['department'] = $index;
        } elseif (in_array($header, ['company', 'organization'])) {
            $headerMap['company'] = $index;
        } elseif (in_array($header, ['phone', 'whatsapp', 'mobile'])) {
            $headerMap['phone'] = $index;
        }
    }
    
    if (!isset($headerMap['badge_id']) || !isset($headerMap['name'])) {
        throw new Exception("Required columns missing: Badge ID and Name are required");
    }
    
    $db->beginTransaction();
    
    // Prepare statements
    $checkStmt = $db->prepare("SELECT id FROM users WHERE badge_id = ?");
    $insertStmt = $db->prepare("
        INSERT INTO users (badge_id, employee_id, name, gender, department_name, company_name, whatsapp_number, employee_list_member) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $updateStmt = $db->prepare("
        UPDATE users SET 
            employee_id = COALESCE(NULLIF(?, ''), employee_id),
            name = COALESCE(NULLIF(?, ''), name),
            gender = ?, 
            department_name = COALESCE(NULLIF(?, ''), department_name),
            company_name = COALESCE(NULLIF(?, ''), company_name),
            whatsapp_number = COALESCE(NULLIF(?, ''), whatsapp_number)
        WHERE badge_id = ?
    ");
    
    $imported = 0;
    $updated = 0;
    $errors = [];
    
    while (($row = fgetcsv($file)) !== false) {
        if (count($row) < count($headers)) {
            continue; // Skip incomplete rows
        }
        
        try {
            $badgeId = isset($headerMap['badge_id']) ? trim($row[$headerMap['badge_id']]) : '';
            $employeeId = isset($headerMap['employee_id']) ? trim($row[$headerMap['employee_id']]) : '';
            $name = isset($headerMap['name']) ? trim($row[$headerMap['name']]) : '';
            $gender = isset($headerMap['gender']) ? strtoupper(trim($row[$headerMap['gender']])) : '';
            $department = isset($headerMap['department']) ? trim($row[$headerMap['department']]) : '';
            $company = isset($headerMap['company']) ? trim($row[$headerMap['company']]) : '';
            $phone = isset($headerMap['phone']) ? trim($row[$headerMap['phone']]) : '';
            
            // Normalize gender values
            if (in_array($gender, ['M', 'MALE', '1'])) {
                $gender = 'M';
            } elseif (in_array($gender, ['F', 'FEMALE', '2'])) {
                $gender = 'F';
            } else {
                $gender = '';
            }
            
            // Normalize badge ID (pad with zeros if numeric)
            if (ctype_digit($badgeId) && strlen($badgeId) < 5) {
                $badgeId = str_pad($badgeId, 5, '0', STR_PAD_LEFT);
            }
            
            if (empty($badgeId) || empty($name)) {
                $errors[] = "Skipping row: Missing badge ID or name - Badge: '$badgeId', Name: '$name'";
                continue;
            }
            
            // Check if employee exists
            $checkStmt->execute([$badgeId]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists) {
                // Update existing employee
                $updateStmt->execute([
                    $employeeId, $name, $gender, $department, $company, $phone, $badgeId
                ]);
                $updated++;
                echo "Updated: $badgeId - $name ($gender)\n";
            } else {
                // Insert new employee
                $insertStmt->execute([
                    $badgeId, $employeeId, $name, $gender, $department, $company, $phone
                ]);
                $imported++;
                echo "Imported: $badgeId - $name ($gender)\n";
            }
            
        } catch (Exception $e) {
            $errors[] = "Error processing row: " . $e->getMessage();
        }
    }
    
    fclose($file);
    $db->commit();
    
    echo "\n=== Import Summary ===\n";
    echo "New employees imported: $imported\n";
    echo "Existing employees updated: $updated\n";
    echo "Total processed: " . ($imported + $updated) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    }
    
    // Display final gender statistics
    echo "\n=== Gender Statistics ===\n";
    $genderStats = getGenderStats($db);
    echo "Male employees: " . $genderStats['male_count'] . "\n";
    echo "Female employees: " . $genderStats['female_count'] . "\n";
    echo "Total employees: " . $genderStats['total_count'] . "\n";
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo "Import failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>