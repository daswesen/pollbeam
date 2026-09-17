<?php
require 'config.php';
session_start();

$db = getDb();

// simple login
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
    <html lang="en"><head><meta charset="UTF-8"><title>PollBeam – Login</title></head>
    <body style="font-family: sans-serif; max-width: 400px; margin: 100px auto;">
        <h2>Moderator Login</h2>
        <form method="post">
            <input type="password" name="login_password" placeholder="Password" style="padding:8px; width:100%;">
            <button type="submit" style="margin-top:10px; padding:8px 16px;">Log in</button>
        </form>
    </body></html>
    <?php
    exit;
}

// Actions (after login)
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
<html lang="en">
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

<p><a href="?logout=1">Log out</a></p>

<h2>Create new session</h2>
<form method="post">
    <div class="field"><input name="new_session_code" placeholder="Short code without spaces, e.g. talk2026" required></div>
    <div class="field"><input name="new_session_title" placeholder="Title (optional)"></div>
    <button type="submit">Create</button>
</form>
<p style="color:#666; font-size:0.9em;">Tip: a short code without spaces or special characters (e.g. <code>talk2026</code>) gives you the shortest participant link.</p>

<h2>Sessions</h2>
<table>
<tr><th>Code</th><th>Title</th><th>Participant link</th><th>QR code</th><th>Presenter link</th><th>Action</th></tr>
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
    <td><img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR code" width="90" height="90"></td>
    <td><a href="<?= htmlspecialchars($baseUrl . '/' . rawurlencode($s['code']) . '/presenter') ?>" target="_blank">Presenter</a></td>
    <td>
        <form class="inline" method="post" onsubmit="return confirm('Really delete session &quot;<?= htmlspecialchars(addslashes($s['code'])) ?>&quot;? All related polls will be deleted too.');">
            <input type="hidden" name="delete_session" value="<?= $s['id'] ?>">
            <button type="submit" style="background:#dc2626; color:white;">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>

<h2>Create new poll</h2>
<form method="post">
    <div class="field">
        <select name="new_poll_session_id" required>
            <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field"><input name="new_poll_question" placeholder="Question" required></div>
    <div class="field">
        <label><input type="radio" name="new_poll_type" value="choice" checked onclick="document.getElementById('poll-options-field').style.display='block'"> Choice answers</label>
        &nbsp;&nbsp;
        <label><input type="radio" name="new_poll_type" value="open" onclick="document.getElementById('poll-options-field').style.display='none'"> Free text</label>
    </div>
    <div class="field" id="poll-options-field"><textarea name="new_poll_options" placeholder="Answer options, one per line" rows="4"></textarea></div>
    <button type="submit">Create poll (becomes active immediately)</button>
</form>
<p style="color:#666; font-size:0.9em;">Note: a new poll automatically becomes the active poll for that session (the most recent one wins).</p>

<h2>Past polls</h2>
<table>
<tr><th>Date/Time</th><th>Session</th><th>Question</th><th>Type</th><th>Status</th><th>Export</th></tr>
<?php foreach ($polls as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['created_at']) ?></td>
    <td><?= htmlspecialchars($p['session_code']) ?></td>
    <td><?= htmlspecialchars($p['question']) ?></td>
    <td><?= $p['type'] === 'open' ? 'Free text' : 'Choice' ?></td>
    <td><?= $p['active'] ? 'active' : '—' ?></td>
    <td><a href="export.php?poll_id=<?= $p['id'] ?>">Download CSV</a></td>
</tr>
<?php endforeach; ?>
<?php if (empty($polls)): ?>
<tr><td colspan="6" style="color:#666;">No polls yet.</td></tr>
<?php endif; ?>
</table>

</body>
</html>
