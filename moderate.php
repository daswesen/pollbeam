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

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function insertPollFromContent($db, $sessionId, $question, $type, $optionsText) {
    $stmt = $db->prepare("INSERT INTO polls (session_id, question, type) VALUES (?, ?, ?)");
    $stmt->execute([$sessionId, $question, $type]);
    $pollId = $db->lastInsertId();

    if ($type === 'choice') {
        $options = array_filter(array_map('trim', explode("\n", $optionsText ?? '')));
        $stmt = $db->prepare("INSERT INTO poll_options (poll_id, label) VALUES (?, ?)");
        foreach ($options as $opt) {
            $stmt->execute([$pollId, $opt]);
        }
    }
    return $pollId;
}

$sections = ['sessions', 'polls', 'templates', 'results'];
$section = $_POST['s'] ?? $_GET['s'] ?? 'sessions';
if (!in_array($section, $sections, true)) {
    $section = 'sessions';
}
// Optional session filter in the results list
$filterSession = (int)($_POST['f'] ?? $_GET['f'] ?? 0);

// Actions (after login)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_session_code'])) {
        $language = ($_POST['new_session_language'] ?? 'en') === 'de' ? 'de' : 'en';
        $stmt = $db->prepare("INSERT INTO sessions (code, title, language) VALUES (?, ?, ?)");
        $stmt->execute([trim($_POST['new_session_code']), trim($_POST['new_session_title'] ?? ''), $language]);
    }
    if (isset($_POST['delete_session'])) {
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_session']]);
    }
    if (isset($_POST['delete_poll'])) {
        $stmt = $db->prepare("DELETE FROM polls WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_poll']]);
    }
    if (isset($_POST['new_poll_session_id'])) {
        $type = ($_POST['new_poll_type'] ?? 'choice') === 'open' ? 'open' : 'choice';
        insertPollFromContent(
            $db,
            (int)$_POST['new_poll_session_id'],
            trim($_POST['new_poll_question']),
            $type,
            $_POST['new_poll_options'] ?? ''
        );
    }
    if (isset($_POST['new_template_name'])) {
        $type = ($_POST['new_template_type'] ?? 'choice') === 'open' ? 'open' : 'choice';
        $stmt = $db->prepare("INSERT INTO poll_templates (name, question, type, options_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['new_template_name']),
            trim($_POST['new_template_question']),
            $type,
            $type === 'choice' ? ($_POST['new_template_options'] ?? '') : null,
        ]);
    }
    if (isset($_POST['delete_template'])) {
        $stmt = $db->prepare("DELETE FROM poll_templates WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_template']]);
    }
    if (isset($_POST['activate_template_id']) && isset($_POST['activate_session_id'])) {
        $stmt = $db->prepare("SELECT question, type, options_text FROM poll_templates WHERE id = ?");
        $stmt->execute([(int)$_POST['activate_template_id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($template) {
            insertPollFromContent(
                $db,
                (int)$_POST['activate_session_id'],
                $template['question'],
                $template['type'],
                $template['options_text']
            );
        }
    }
    $target = 'moderate.php?s=' . urlencode($section);
    if ($section === 'results' && $filterSession > 0) {
        $target .= '&f=' . $filterSession;
    }
    header('Location: ' . $target);
    exit;
}

$sessions = $db->query("SELECT id, code, title, language FROM sessions ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$templates = $db->query("SELECT id, name, question, type, options_text FROM poll_templates ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pollCount = (int)$db->query("SELECT COUNT(*) FROM polls")->fetchColumn();
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];

// Data for the results area
$detailPoll = null;
$detailOptions = [];
$detailResponses = [];
$polls = [];
if ($section === 'results') {
    $detailId = (int)($_GET['poll'] ?? 0);
    if ($detailId > 0) {
        $stmt = $db->prepare(
            "SELECT p.id, p.question, p.type, p.created_at, s.code AS session_code, s.id AS session_id
             FROM polls p JOIN sessions s ON p.session_id = s.id
             WHERE p.id = ?"
        );
        $stmt->execute([$detailId]);
        $detailPoll = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($detailPoll) {
            if ($detailPoll['type'] === 'open') {
                $stmt = $db->prepare("SELECT text, created_at FROM poll_responses WHERE poll_id = ? ORDER BY created_at ASC, id ASC");
                $stmt->execute([$detailId]);
                $detailResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $db->prepare("SELECT label, votes FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
                $stmt->execute([$detailId]);
                $detailOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
    if (!$detailPoll) {
        $sql = "SELECT p.id, p.question, p.type, p.created_at, s.code AS session_code,
                       (SELECT COUNT(*) FROM poll_responses r WHERE r.poll_id = p.id) AS n_responses,
                       (SELECT COALESCE(SUM(o.votes), 0) FROM poll_options o WHERE o.poll_id = p.id) AS n_votes
                FROM polls p JOIN sessions s ON p.session_id = s.id";
        $params = [];
        if ($filterSession > 0) {
            $sql .= " WHERE p.session_id = ?";
            $params[] = $filterSession;
        }
        $sql .= " ORDER BY p.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$navItems = [
    'sessions'  => ['Sessions', count($sessions)],
    'polls'     => ['Create poll', null],
    'templates' => ['Question templates', count($templates)],
    'results'   => ['Results', $pollCount],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PollBeam – Moderation</title>
<style>
* { box-sizing: border-box; }
body { font-family: -apple-system, sans-serif; margin: 0; color: #111827; background: #f7f7f8; }
.layout { display: flex; min-height: 100vh; }
nav.side { width: 220px; flex-shrink: 0; background: #111827; color: white; padding: 20px 0; display: flex; flex-direction: column; }
nav.side .brand { font-weight: bold; font-size: 1.1em; padding: 0 20px 16px; }
nav.side a { display: flex; justify-content: space-between; align-items: center; color: #d1d5db; text-decoration: none; padding: 10px 20px; }
nav.side a:hover { background: #1f2937; color: white; }
nav.side a.active { background: #2563eb; color: white; }
nav.side .count { font-size: 0.8em; opacity: 0.8; }
nav.side .spacer { flex: 1; }
main { flex: 1; padding: 24px 32px; max-width: 1000px; }
h1 { margin-top: 0; font-size: 1.4em; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
td, th { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
th { background: #f3f4f6; font-size: 0.9em; }
button { padding: 5px 12px; cursor: pointer; }
button.danger { background: #dc2626; color: white; border: none; border-radius: 4px; }
button.primary { background: #2563eb; color: white; border: none; border-radius: 4px; padding: 8px 16px; }
form.inline { display: inline; }
input, textarea, select { padding: 8px; width: 100%; font: inherit; }
.field { margin-bottom: 10px; }
.card { background: white; border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
.hint { color: #6b7280; font-size: 0.9em; }
.empty { color: #6b7280; }
.template-row form.inline select { width: auto; display: inline-block; }
.filter select { width: auto; display: inline-block; }
.bar { background: #e5e7eb; border-radius: 4px; height: 10px; overflow: hidden; margin-top: 4px; }
.bar-fill { background: #2563eb; height: 100%; }
.answer { background: white; border-radius: 6px; padding: 8px 12px; margin-bottom: 6px; display: flex; justify-content: space-between; gap: 16px; }
.answer .time { color: #6b7280; font-size: 0.85em; white-space: nowrap; }
@media (max-width: 760px) {
  .layout { flex-direction: column; }
  nav.side { width: auto; flex-direction: row; flex-wrap: wrap; padding: 8px; }
  nav.side .brand { width: 100%; padding: 4px 12px; }
  nav.side a { padding: 8px 12px; }
  nav.side .spacer { display: none; }
  main { padding: 16px; }
  table { display: block; overflow-x: auto; }
}
</style>
</head>
<body>
<div class="layout">

<nav class="side">
    <div class="brand">PollBeam</div>
    <?php foreach ($navItems as $key => $item): ?>
    <a href="?s=<?= $key ?>" class="<?= $section === $key ? 'active' : '' ?>">
        <span><?= h($item[0]) ?></span>
        <?php if ($item[1] !== null): ?><span class="count"><?= (int)$item[1] ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
    <div class="spacer"></div>
    <a href="?logout=1">Log out</a>
</nav>

<main>

<?php if ($section === 'sessions'): ?>

<h1>Sessions</h1>

<div class="card">
    <strong>Create new session</strong>
    <form method="post" style="margin-top:10px;">
        <input type="hidden" name="s" value="sessions">
        <div class="field"><input name="new_session_code" placeholder="Short code without spaces, e.g. talk2026" required></div>
        <div class="field"><input name="new_session_title" placeholder="Title (optional)"></div>
        <div class="field">
            <label><input type="radio" name="new_session_language" value="en" checked style="width:auto;"> English</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="new_session_language" value="de" style="width:auto;"> German</label>
        </div>
        <button type="submit" class="primary">Create</button>
    </form>
    <p class="hint">Tip: a short code without spaces or special characters (e.g. <code>talk2026</code>) gives you the shortest participant link. Language only affects the interface (buttons, hints). You type poll questions yourself in whichever language you like.</p>
</div>

<table>
<tr><th>Code</th><th>Title</th><th>Language</th><th>Participant link</th><th>QR code</th><th>Presenter</th><th>Action</th></tr>
<?php foreach ($sessions as $s):
    $shortUrl = $baseUrl . '/' . rawurlencode($s['code']);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . urlencode($shortUrl);
?>
<tr>
    <td><?= h($s['code']) ?></td>
    <td><?= h($s['title']) ?></td>
    <td><?= $s['language'] === 'de' ? 'DE' : 'EN' ?></td>
    <td><a href="<?= h($shortUrl) ?>" target="_blank"><?= h($shortUrl) ?></a></td>
    <td><img src="<?= h($qrUrl) ?>" alt="QR code" width="90" height="90" loading="lazy"></td>
    <td><a href="<?= h($baseUrl . '/' . rawurlencode($s['code']) . '/presenter') ?>" target="_blank">Presenter</a></td>
    <td>
        <a href="export.php?session_id=<?= (int)$s['id'] ?>">CSV (all polls)</a>
        <form class="inline" method="post" onsubmit="return confirm('Really delete session &quot;<?= h(addslashes($s['code'])) ?>&quot;? All related polls and results will be deleted too.');">
            <input type="hidden" name="s" value="sessions">
            <input type="hidden" name="delete_session" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($sessions)): ?>
<tr><td colspan="7" class="empty">No sessions yet.</td></tr>
<?php endif; ?>
</table>

<?php elseif ($section === 'polls'): ?>

<h1>Create poll</h1>

<div class="card">
    <form method="post">
        <input type="hidden" name="s" value="polls">
        <div class="field">
            <select name="new_poll_session_id" required>
                <?php foreach ($sessions as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= h($s['code']) ?><?= $s['title'] !== '' ? ' (' . h($s['title']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><input name="new_poll_question" placeholder="Question" required></div>
        <div class="field">
            <label><input type="radio" name="new_poll_type" value="choice" checked style="width:auto;" onclick="document.getElementById('poll-options-field').style.display='block'"> Choice answers</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="new_poll_type" value="open" style="width:auto;" onclick="document.getElementById('poll-options-field').style.display='none'"> Free text</label>
        </div>
        <div class="field" id="poll-options-field"><textarea name="new_poll_options" placeholder="Answer options, one per line" rows="4"></textarea></div>
        <button type="submit" class="primary">Create poll (becomes active immediately)</button>
    </form>
    <p class="hint">A new poll automatically becomes the active poll for that session (the most recent one wins). You can see results under "Results".</p>
</div>

<?php elseif ($section === 'templates'): ?>

<h1>Question templates</h1>
<p class="hint">Set up recurring questions (e.g. intro/outro questions) once and activate them into a session with one click, without retyping them every time.</p>

<div class="card">
    <strong>New template</strong>
    <form method="post" style="margin-top:10px;">
        <input type="hidden" name="s" value="templates">
        <div class="field"><input name="new_template_name" placeholder="Template name, e.g. Intro question" required></div>
        <div class="field"><input name="new_template_question" placeholder="Question" required></div>
        <div class="field">
            <label><input type="radio" name="new_template_type" value="choice" checked style="width:auto;" onclick="document.getElementById('template-options-field').style.display='block'"> Choice answers</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="new_template_type" value="open" style="width:auto;" onclick="document.getElementById('template-options-field').style.display='none'"> Free text</label>
        </div>
        <div class="field" id="template-options-field"><textarea name="new_template_options" placeholder="Answer options, one per line" rows="4"></textarea></div>
        <button type="submit" class="primary">Save template</button>
    </form>
</div>

<table>
<tr><th>Name</th><th>Question</th><th>Type</th><th>Activate in session</th><th>Action</th></tr>
<?php foreach ($templates as $tpl): ?>
<tr class="template-row">
    <td><?= h($tpl['name']) ?></td>
    <td><?= h($tpl['question']) ?></td>
    <td><?= $tpl['type'] === 'open' ? 'Free text' : 'Choice' ?></td>
    <td>
        <form class="inline" method="post">
            <input type="hidden" name="s" value="templates">
            <input type="hidden" name="activate_template_id" value="<?= (int)$tpl['id'] ?>">
            <select name="activate_session_id" required>
                <?php foreach ($sessions as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= h($s['code']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="primary">Activate</button>
        </form>
    </td>
    <td>
        <form class="inline" method="post" onsubmit="return confirm('Really delete template &quot;<?= h(addslashes($tpl['name'])) ?>&quot;?');">
            <input type="hidden" name="s" value="templates">
            <input type="hidden" name="delete_template" value="<?= (int)$tpl['id'] ?>">
            <button type="submit" class="danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($templates)): ?>
<tr><td colspan="5" class="empty">No templates yet.</td></tr>
<?php endif; ?>
</table>

<?php elseif ($section === 'results' && $detailPoll): ?>

<p><a href="?s=results<?= $filterSession > 0 ? '&f=' . $filterSession : '' ?>">&larr; Back to results</a></p>
<h1><?= h($detailPoll['question']) ?></h1>
<p class="hint">
    Session <strong><?= h($detailPoll['session_code']) ?></strong> ·
    created <?= h($detailPoll['created_at']) ?> ·
    <?= $detailPoll['type'] === 'open' ? 'Free text' : 'Choice' ?>
</p>

<p>
    <a href="export.php?poll_id=<?= (int)$detailPoll['id'] ?>">Download CSV</a>
    &nbsp;
    <form class="inline" method="post" onsubmit="return confirm('Permanently delete this poll with all its results?');">
        <input type="hidden" name="s" value="results">
        <input type="hidden" name="f" value="<?= $filterSession > 0 ? $filterSession : 0 ?>">
        <input type="hidden" name="delete_poll" value="<?= (int)$detailPoll['id'] ?>">
        <button type="submit" class="danger">Delete poll</button>
    </form>
</p>

<?php if ($detailPoll['type'] === 'open'): ?>
    <p><strong><?= count($detailResponses) ?></strong> answers</p>
    <?php foreach ($detailResponses as $r): ?>
    <div class="answer"><span><?= nl2br(h($r['text'])) ?></span><span class="time"><?= h($r['created_at']) ?></span></div>
    <?php endforeach; ?>
    <?php if (empty($detailResponses)): ?><p class="empty">No answers.</p><?php endif; ?>
<?php else:
    $totalVotes = 0;
    foreach ($detailOptions as $o) { $totalVotes += (int)$o['votes']; }
?>
    <p><strong><?= $totalVotes ?></strong> votes</p>
    <div class="card">
    <?php foreach ($detailOptions as $o):
        $pct = $totalVotes > 0 ? round(((int)$o['votes'] / $totalVotes) * 100) : 0;
    ?>
        <div style="margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between;"><span><?= h($o['label']) ?></span><span><?= $pct ?>% (<?= (int)$o['votes'] ?>)</span></div>
            <div class="bar"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($detailOptions)): ?><span class="empty">No options.</span><?php endif; ?>
    </div>
<?php endif; ?>

<?php elseif ($section === 'results'): ?>

<h1>Results</h1>

<form method="get" class="filter">
    <input type="hidden" name="s" value="results">
    <label>Session:
        <select name="f" onchange="this.form.submit()">
            <option value="0">All sessions</option>
            <?php foreach ($sessions as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $filterSession === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['code']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php if ($filterSession > 0): ?>
<p><a href="export.php?session_id=<?= $filterSession ?>">Download all results of this session as one CSV</a></p>
<?php else: ?>
<p><a href="export.php?all=1">Download all results of all sessions as one CSV</a></p>
<?php endif; ?>

<table>
<tr><th>Date/Time</th><th>Session</th><th>Question</th><th>Type</th><th>Answers</th><th>Action</th></tr>
<?php foreach ($polls as $p): ?>
<tr>
    <td><?= h($p['created_at']) ?></td>
    <td><?= h($p['session_code']) ?></td>
    <td><a href="?s=results&amp;poll=<?= (int)$p['id'] ?><?= $filterSession > 0 ? '&amp;f=' . $filterSession : '' ?>"><?= h($p['question']) ?></a></td>
    <td><?= $p['type'] === 'open' ? 'Free text' : 'Choice' ?></td>
    <td><?= $p['type'] === 'open' ? (int)$p['n_responses'] : (int)$p['n_votes'] ?></td>
    <td>
        <a href="?s=results&amp;poll=<?= (int)$p['id'] ?><?= $filterSession > 0 ? '&amp;f=' . $filterSession : '' ?>">View</a> ·
        <a href="export.php?poll_id=<?= (int)$p['id'] ?>">CSV</a>
        <form class="inline" method="post" onsubmit="return confirm('Permanently delete poll &quot;<?= h(addslashes($p['question'])) ?>&quot; with all its results?');">
            <input type="hidden" name="s" value="results">
            <input type="hidden" name="f" value="<?= $filterSession > 0 ? $filterSession : 0 ?>">
            <input type="hidden" name="delete_poll" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($polls)): ?>
<tr><td colspan="6" class="empty">No polls yet.</td></tr>
<?php endif; ?>
</table>

<?php endif; ?>

</main>
</div>
</body>
</html>
