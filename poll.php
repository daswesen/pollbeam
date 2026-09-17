<?php
header('Content-Type: application/json');
require 'config.php';

$db = getDb();
$code = trim($_GET['code'] ?? '');

if ($code === '') {
    echo json_encode(['ok' => false, 'error' => 'Kein Code angegeben']);
    exit;
}

$stmt = $db->prepare("SELECT id, title FROM sessions WHERE code = ?");
$stmt->execute([$code]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['ok' => false, 'error' => 'Session nicht gefunden']);
    exit;
}

$sessionId = $session['id'];

// aktive Umfrage inkl. Optionen bzw. Freitext-Antworten
$stmt = $db->prepare(
    "SELECT id, question, type FROM polls
     WHERE session_id = ? AND active = 1
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->execute([$sessionId]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

$pollData = null;
if ($poll) {
    $pollData = [
        'id' => $poll['id'],
        'question' => $poll['question'],
        'type' => $poll['type']
    ];

    if ($poll['type'] === 'open') {
        $stmt = $db->prepare(
            "SELECT text FROM poll_responses WHERE poll_id = ? ORDER BY created_at DESC LIMIT 100"
        );
        $stmt->execute([$poll['id']]);
        $pollData['responses'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $stmt = $db->prepare("SELECT id, label, votes FROM poll_options WHERE poll_id = ?");
        $stmt->execute([$poll['id']]);
        $pollData['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

echo json_encode([
    'ok' => true,
    'title' => $session['title'],
    'poll' => $pollData
]);
