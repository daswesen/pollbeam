<?php
require 'config.php';
session_start();

if (empty($_SESSION['is_mod'])) {
    http_response_code(403);
    echo 'Not logged in.';
    exit;
}

$db = getDb();

function safeName($s) {
    return preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$s);
}

// Export of ALL polls of one session as a single flat CSV
// (one row per answer or per option), oldest poll first.
$exportAll = isset($_GET['all']);
if ($exportAll || isset($_GET['session_id'])) {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if ($exportAll) {
        $session = ['id' => 0, 'code' => 'all'];
    } else {
        $stmt = $db->prepare("SELECT id, code FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$session) {
        http_response_code(404);
        echo 'Session not found.';
        exit;
    }

    $sql = "SELECT p.id, p.question, p.type, p.created_at, s.code AS session_code
            FROM polls p JOIN sessions s ON p.session_id = s.id"
         . ($exportAll ? "" : " WHERE p.session_id = ?")
         . " ORDER BY p.created_at ASC, p.id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($exportAll ? [] : [$sessionId]);
    $sessionPolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'session_' . safeName($session['code']) . '_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel displays special characters correctly
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Session', 'Poll created at', 'Question', 'Type', 'Answer or option', 'Timestamp', 'Votes']);

    foreach ($sessionPolls as $p) {
        $typeLabel = $p['type'] === 'open' ? 'Free text' : 'Choice';
        if ($p['type'] === 'open') {
            $stmt = $db->prepare("SELECT text, created_at FROM poll_responses WHERE poll_id = ? ORDER BY created_at ASC, id ASC");
            $stmt->execute([$p['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                fputcsv($out, [$p['session_code'], $p['created_at'], $p['question'], $typeLabel, $row['text'], $row['created_at'], '']);
            }
        } else {
            $stmt = $db->prepare("SELECT label, votes FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
            $stmt->execute([$p['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                fputcsv($out, [$p['session_code'], $p['created_at'], $p['question'], $typeLabel, $row['label'], '', $row['votes']]);
            }
        }
    }

    fclose($out);
    exit;
}

// Export of a single poll
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
    echo 'Poll not found.';
    exit;
}

$filename = 'poll_' . safeName($poll['session_code']) . '_' . date('Y-m-d_His', strtotime($poll['created_at'])) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel displays special characters correctly
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Session', $poll['session_code']]);
fputcsv($out, ['Question', $poll['question']]);
fputcsv($out, ['Poll created at', $poll['created_at']]);
fputcsv($out, []);

if ($poll['type'] === 'open') {
    fputcsv($out, ['Answer', 'Timestamp']);
    $stmt = $db->prepare("SELECT text, created_at FROM poll_responses WHERE poll_id = ? ORDER BY created_at ASC");
    $stmt->execute([$pollId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [$row['text'], $row['created_at']]);
    }
} else {
    fputcsv($out, ['Option', 'Votes']);
    $stmt = $db->prepare("SELECT label, votes FROM poll_options WHERE poll_id = ?");
    $stmt->execute([$pollId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [$row['label'], $row['votes']]);
    }
}

fclose($out);
