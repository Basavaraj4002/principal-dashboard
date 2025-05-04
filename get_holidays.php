<?php
// get_holidays.php
require 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('Invalid request method.', 405);
}

try {
    $sql = "SELECT holiday_date, name FROM holidays ORDER BY holiday_date ASC";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format as key-value pair (date => name) as expected by JS
    $holidays = [];
    foreach ($results as $row) {
        $holidays[$row['holiday_date']] = $row['name'];
    }

    sendJsonResponse(['success' => true, 'holidays' => $holidays]);

} catch (PDOException $e) {
    sendJsonError('Failed to fetch holidays: ' . $e->getMessage(), 500);
}
?>