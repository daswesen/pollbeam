<?php
require 'config.php';
session_start();

if (empty($_SESSION['is_mod'])) {
    http_response_code(403);
    echo 'Nicht angemeldet.';
    exit;
}

$db = getDb();
$pollId = (int)($_GET['poll_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT p.id, p.question, p.type, p.created_at, s.code AS session_code
     FROM polls p JOIN sessions s ON p.session_id = s.id
     WHERE p.id = ?"
);
$stmt->execute([$pollId]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poll) {
    http_response_code(404);
    echo 'Umfrage nicht gefunden.';
    exit;
}

$filename = 'umfrage_' . $poll['session_code'] . '_' . date('Y-m-d_His', strtotime($poll['created_at'])) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM, damit Excel Umlaute korrekt anzeigt
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Session', $poll['session_code']]);
fputcsv($out, ['Frage', $poll['question']]);
fputcsv($out, ['Umfrage angelegt am', $poll['created_at']]);
fputcsv($out, []);

if ($poll['type'] === 'open') {
    fputcsv($out, ['Antwort', 'Zeitstempel']);
    $stmt = $db->prepare("SELECT text, created_at FROM poll_responses WHERE poll_id = ? ORDER BY created_at ASC");
    $stmt->execute([$pollId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [$row['text'], $row['created_at']]);
    }
} else {
    fputcsv($out, ['Option', 'Stimmen']);
    $stmt = $db->prepare("SELECT label, votes FROM poll_options WHERE poll_id = ?");
    $stmt->execute([$pollId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [$row['label'], $row['votes']]);
    }
}

fclose($out);
