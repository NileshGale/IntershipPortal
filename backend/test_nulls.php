<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT first_name, last_name, CONCAT(first_name, ' ', last_name) as student_name FROM students");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Null Count: ";
$nulls = 0;
foreach($data as $row) {
    if ($row['student_name'] === null) {
        $nulls++;
    }
}
echo $nulls . "\n";
