<?php
// Kopiere diese Datei nach config.php und trag deine eigenen Zugangsdaten ein.
// config.php ist in .gitignore und wird NICHT ins Repository übernommen.

$DB_HOST = 'localhost';
$DB_NAME = 'deine_datenbank';
$DB_USER = 'dein_benutzername';
$DB_PASS = 'dein_passwort';

// Passwort für die Moderationsseite (moderate.php) -- unbedingt ändern!
$MODERATOR_PASSWORD = 'bitte-aendern';

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
