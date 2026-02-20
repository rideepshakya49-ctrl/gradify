<?php
// Test Regex Logic locally since we cannot easily submit form via cli without curl
// Mirroring the logic added to register.php

$test_cases = [
    'user123' => false,
    'username' => true,
    'UserName' => true,
    'user name' => false,
    '12345' => false,
    'user_name' => false,
    'user.name' => false
];

echo "Testing Username Validation Pattern: /^[a-zA-Z]+$/\n";
echo "------------------------------------------------\n";

foreach ($test_cases as $input => $expected) {
    $result = preg_match("/^[a-zA-Z]+$/", $input);
    $pass = ($result == 1) === $expected;
    
    echo sprintf("Input: '%s' -> Result: %s | Expected: %s | %s\n",
        $input,
        $result ? "PASS" : "FAIL",
        $expected ? "PASS" : "FAIL",
        $pass ? "OK" : "ERROR"
    );
}
?>