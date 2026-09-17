<?php
header('Content-Type: application/json');
require 'config.php';

$db = getDb();
$action = $_POST['action'] ?? '';

if ($action === 'poll_answer') {
    $pollId = (int)($_POST['poll_id'] ?? 0);
    $text = trim($_POST['text'] ?? '');

    if ($pollId <= 0 || $text === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing data']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO poll_responses (poll_id, text) VALUES (?, ?)");
    $stmt->execute([$pollId, $text]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'poll_vote') {
    $optionId = (int)($_POST['option_id'] ?? 0);
    if ($optionId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid option']);
        exit;
    }

    $stmt = $db->prepare("UPDATE poll_options SET votes = votes + 1 WHERE id = ?");
    $stmt->execute([$optionId]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
