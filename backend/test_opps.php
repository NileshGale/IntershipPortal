<?php
require 'includes/db.php';
try {
    $cid = 1; // Assuming amazon
    $opps = $pdo->prepare("SELECT o.*, (SELECT COUNT(*) FROM applications WHERE opportunity_id=o.id) as app_count FROM opportunities o WHERE o.company_id=? ORDER BY o.created_at DESC");
    $opps->execute([$cid]);
    $data = $opps->fetchAll(PDO::FETCH_ASSOC);
    echo "Success! \n";
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
