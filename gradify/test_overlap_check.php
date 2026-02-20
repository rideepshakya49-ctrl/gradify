<?php
require_once 'database.php';

// Test configuration
$test_user_id = 999; // Using a high ID to avoid conflict, or we can insert a temp user
$day = 'Monday';

// Cleanup prev run
$pdo->prepare("DELETE FROM routine_activities WHERE user_id = ?")->execute([$test_user_id]);

// Insert base routine: 10:00 - 11:00
$stmt = $pdo->prepare("INSERT INTO routine_activities (user_id, day_of_week, activity, start_time, end_time) VALUES (?, ?, 'Base Routine', '10:00:00', '11:00:00')");
$stmt->execute([$test_user_id, $day]);

echo "Base routine inserted: 10:00 - 11:00\n";

// Test Cases
$test_cases = [
    ['name' => 'No Overlap (After)', 'start' => '11:00:00', 'end' => '12:00:00', 'expected' => false],
    ['name' => 'No Overlap (Before)', 'start' => '09:00:00', 'end' => '10:00:00', 'expected' => false],
    ['name' => 'Overlap (Start Inside)', 'start' => '10:30:00', 'end' => '11:30:00', 'expected' => true],
    ['name' => 'Overlap (End Inside)', 'start' => '09:30:00', 'end' => '10:30:00', 'expected' => true],
    ['name' => 'Overlap (Engulfing)', 'start' => '09:00:00', 'end' => '12:00:00', 'expected' => true],
    ['name' => 'Overlap (Inside)', 'start' => '10:15:00', 'end' => '10:45:00', 'expected' => true],
    ['name' => 'Exact Match', 'start' => '10:00:00', 'end' => '11:00:00', 'expected' => true],
];

foreach ($test_cases as $case) {
    // Logic from routines.php
    $start_time = $case['start'];
    $end_time = $case['end'];
    
    // Using user query logic
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM routine_activities 
                          WHERE user_id = ? AND day_of_week = ? 
                          AND start_time < ? AND end_time > ?");
    $stmt->execute([$test_user_id, $day, $end_time, $start_time]);
    $count = $stmt->fetchColumn();
    
    $is_conflict = $count > 0;
    
    $result_str = $is_conflict ? "CONFLICT" : "OK";
    $expected_str = $case['expected'] ? "CONFLICT" : "OK";
    $pass = $is_conflict === $case['expected'];
    
    echo sprintf("[%s] %s (%s-%s) -> Result: %s | Expected: %s\n", 
        $pass ? "PASS" : "FAIL", 
        $case['name'], $start_time, $end_time, $result_str, $expected_str);
}

// Cleanup
$pdo->prepare("DELETE FROM routine_activities WHERE user_id = ?")->execute([$test_user_id]);
echo "Cleanup done.\n";
?>