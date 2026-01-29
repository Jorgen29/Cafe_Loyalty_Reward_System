<?php
require_once '../../public/actions/auth/db_config.php';

$currentYear = date("Y");

if (date("m-d") === "12-01") { // Only run on Jan 1
    $deleteStmt = $conn->prepare("
        DELETE cr
        FROM customerrewards cr
        INNER JOIN `order` o
            ON cr.customer_id = o.customer_id
           AND cr.reward_id = o.reward_id
        WHERE YEAR(o.order_date) < ?
    ");
    $deleteStmt->bind_param("i", $currentYear);
    $deleteStmt->execute();
    $deleteStmt->close();
}