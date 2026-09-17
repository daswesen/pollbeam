<?php
require 'config.php';
session_start();

$db = getDb();

// einfacher Login
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $MODERATOR_PASSWORD) {
        $_SESSION['is_mod'] = true;
    }
}
if (!empty($_GET['logout'])) {
    session_destroy();
    header('Location: moderate.php');
    exit;
}

if (empty($_SESSION['is_mod'])) {
    ?>
    <!DOCTYPE html>
    <html lang="de"><head><meta charset="UTF-8"><title>PollBeam – Login</title></head>
    <body style="font-family: sans-serif; max-width: 400px; margin: 100px auto;">
        <h2>Moderation Login</h2>
        <form method="post">
            <input type="password" name="login_password" placeholder="Passwort" style="padding:8px; width:100%;">
            <button type="submit" style="margin-top:10px; padding:8px 16px;">Einloggen</button>
        </form>
    </body></html>
    <?php
    exit;
}

// Aktionen (nach Login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_session_code'])) {
        $stmt = $db->prepare("INSERT INTO sessions (code, title) VALUES (?, ?)");
        $stmt->execute([trim($_POST['new_session_code']), trim($_POST['new_session_title'] ?? '')]);
    }
    if (isset($_POST['delete_session'])) {
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_session']]);
    }
    if (isset($_POST['new_poll_session_id'])) {
        $type = ($_POST['new_poll_type'] ?? 'choice') === 'open' ? 'open' : 'choice';
        $stmt = $db->prepare("INSERT INTO polls (session_id, question, type) VALUES (?, ?, ?)");
        $stmt->execute([(int)$_POST['new_poll_session_id'], trim($_POST['new_poll_question']), $type]);
        $pollId = $db->lastInsertId();

        if ($type === 'choice') {
            $options = array_filter(array_map('trim', explode("\n", $_POST['new_poll_options'] ?? '')));
            $stmt = $db->prepare("INSERT INTO poll_options (poll_id, label) VALUES (?, ?)");
            foreach ($options as $opt) {
                $stmt->execute([$pollId, $opt]);
            }
        }
    }
    header('Location: moderate.php');
    exit;
}

$sessions = $db->query("SELECT id, code, title FROM sessions ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$polls = $db->query(
    "SELECT p.id, p.question, p.type, p.active, p.created_at, s.code AS session_code
     FROM polls p JOIN sessions s ON p.session_id = s.id
     ORDER BY p.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>PollBeam – Moderation</title>
<style>
body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; }
h2 { margin-top: 40px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
td, th { padding: 6px 10px; border-bottom: 1px solid #ddd; text-align: left; }
button { padding: 4px 10px; cursor: pointer; }
form.inline { display: inline; }
.hidden-row { opacity: 0.4; }
input, textarea { padding: 6px; width: 100%; box-sizing: border-box; }
.field { margin-bottom: 10px; }
</style>
</head>
<body>

<p><a href="?logout=1">Abmelden</a></p>

<h2>Neue Session anlegen</h2>
<form method="post">
    <div class="field"><input name="new_session_code" placeholder="Kurzer Code ohne Leerzeichen, z.B. ef2026" required></div>
    <div class="field"><input name="new_session_title" placeholder="Titel (optional)"></div>
    <button type="submit">Anlegen</button>
</form>
<p style="color:#666; font-size:0.9em;">Tipp: kurzer Code ohne Leerzeichen/Umlaute (z.B. <code>ef2026</code>) ergibt den kürzesten Teilnehmer-Link.</p>

<h2>Sessions</h2>
<table>
<tr><th>Code</th><th>Titel</th><th>Teilnehmer-Link</th><th>QR-Code</th><th>Presenter-Link</th><th>Aktion</th></tr>
<?php foreach ($sessions as $s):
    $shortUrl = $baseUrl . '/' . rawurlencode($s['code']);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . urlencode($shortUrl);
?>
<tr>
    <td><?= htmlspecialchars($s['code']) ?></td>
    <td><?= htmlspecialchars($s['title']) ?></td>
    <td>
        <a href="<?= htmlspecialchars($shortUrl) ?>" target="_blank"><?= htmlspecialchars($shortUrl) ?></a>
    </td>
    <td><img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR-Code" width="90" height="90"></td>
    <td><a href="<?= htmlspecialchars($baseUrl . '/' . rawurlencode($s['code']) . '/presenter') ?>" target="_blank">Presenter</a></td>
    <td>
        <form class="inline" method="post" onsubmit="return confirm('Session &quot;<?= htmlspecialchars(addslashes($s['code'])) ?>&quot; wirklich löschen? Alle zugehörigen Fragen und Umfragen werden mitgelöscht.');">
            <input type="hidden" name="delete_session" value="<?= $s['id'] ?>">
            <button type="submit" style="background:#dc2626; color:white;">Löschen</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>

<h2>Neue Umfrage anlegen</h2>
<form method="post">
    <div class="field">
        <select name="new_poll_session_id" required>
            <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field"><input name="new_poll_question" placeholder="Frage" required></div>
    <div class="field">
        <label><input type="radio" name="new_poll_type" value="choice" checked onclick="document.getElementById('poll-options-field').style.display='block'"> Auswahlantworten</label>
        &nbsp;&nbsp;
        <label><input type="radio" name="new_poll_type" value="open" onclick="document.getElementById('poll-options-field').style.display='none'"> Freie Texteingabe</label>
    </div>
    <div class="field" id="poll-options-field"><textarea name="new_poll_options" placeholder="Antwortoptionen, eine pro Zeile" rows="4"></textarea></div>
    <button type="submit">Umfrage anlegen (wird sofort aktiv)</button>
</form>
<p style="color:#666; font-size:0.9em;">Hinweis: Eine neue Umfrage wird automatisch die aktive Umfrage der Session (letzte gewinnt).</p>

<h2>Bisherige Umfragen</h2>
<table>
<tr><th>Datum/Zeit</th><th>Session</th><th>Frage</th><th>Typ</th><th>Status</th><th>Export</th></tr>
<?php foreach ($polls as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['created_at']) ?></td>
    <td><?= htmlspecialchars($p['session_code']) ?></td>
    <td><?= htmlspecialchars($p['question']) ?></td>
    <td><?= $p['type'] === 'open' ? 'Freitext' : 'Auswahl' ?></td>
    <td><?= $p['active'] ? 'aktiv' : '—' ?></td>
    <td><a href="export.php?poll_id=<?= $p['id'] ?>">CSV herunterladen</a></td>
</tr>
<?php endforeach; ?>
<?php if (empty($polls)): ?>
<tr><td colspan="6" style="color:#666;">Noch keine Umfragen angelegt.</td></tr>
<?php endif; ?>
</table>

</body>
</html>
