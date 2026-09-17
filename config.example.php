<?php
// Copy this file to config.php and fill in your own credentials.
// config.php is listed in .gitignore and will NOT be committed to the repository.

$DB_HOST = 'localhost';
$DB_NAME = 'your_database';
$DB_USER = 'your_username';
$DB_PASS = 'your_password';

// Password for the moderator page (moderate.php) -- make sure to change this!
$MODERATOR_PASSWORD = 'please-change-me';

function getDb() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return $pdo;
}
