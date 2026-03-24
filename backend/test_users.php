<?php
require 'includes/db.php';
$stmt = $pdo->query("DESCRIBE users");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . "\n";
}
