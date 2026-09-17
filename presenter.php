<?php $serverCode = $_GET['code'] ?? ''; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PollBeam – Presenter</title>
<style>
  body { font-family: -apple-system, sans-serif; margin: 0; padding: 40px; background: #111827; color: white; }
  h1 { font-size: 2em; margin-bottom: 30px; }
  .card { background: #1f2937; border-radius: 12px; padding: 24px; margin-bottom: 20px; max-width: 700px; }
  .poll-badge { display: inline-block; background: #1d4ed8; color: white; font-size: 0.8em; font-weight: bold; letter-spacing: 0.05em; padding: 4px 10px; border-radius: 4px; margin-bottom: 10px; }
  .poll-option { margin-bottom: 16px; }
  .response-item { background: #374151; border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; font-size: 1.15em; }
  .poll-label { display: flex; justify-content: space-between; font-size: 1.2em; margin-bottom: 6px; }
  .bar { background: #374151; border-radius: 6px; height: 20px; overflow: hidden; }
  .bar-fill { background: #10b981; height: 100%; transition: width 0.3s; }
  .code-hint { color: #9ca3af; font-size: 1em; }
  .qr-box { position: fixed; top: 24px; right: 24px; background: white; padding: 10px; border-radius: 10px; text-align: center; }
  .qr-box img { display: block; }
  .qr-box span { color: #111827; font-size: 1.3em; font-weight: bold; display: block; margin-top: 4px; }
</style>
</head>
<body>

<div class="qr-box" id="qr-box" style="display:none;">
  <img id="qr-img" src="" alt="QR-Code" width="260" height="260">
  <span id="qr-code-text"></span>
</div>

<h1 id="session-title">Loading session…</h1>
<p class="code-hint">Participant link: <span id="code-display"></span></p>

<div class="card" id="poll-card" style="display:none;">
  <div class="poll-badge">POLL</div>
  <h2 id="poll-question"></h2>
  <div id="poll-options"></div>
</div>

<script>
const params = new URLSearchParams(window.location.search);
const code = <?= json_encode($serverCode) ?> || params.get('code') || 'demo';

const shortLink = window.location.origin + '/' + encodeURIComponent(code);
document.getElementById('code-display').textContent = shortLink;
document.getElementById('qr-code-text').textContent = code;
document.getElementById('qr-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' + encodeURIComponent(shortLink);
document.getElementById('qr-box').style.display = 'block';

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

async function refresh() {
  try {
    const res = await fetch('/poll.php?code=' + encodeURIComponent(code));
    const data = await res.json();
    if (!data.ok) {
      document.getElementById('session-title').textContent = 'Error: ' + data.error;
      return;
    }
    document.getElementById('session-title').textContent = data.title || 'Live Session';

    const pollCard = document.getElementById('poll-card');
    if (data.poll) {
      pollCard.style.display = 'block';
      document.getElementById('poll-question').textContent = data.poll.question;
      const optDiv = document.getElementById('poll-options');
      optDiv.innerHTML = '';

      if (data.poll.type === 'open') {
        if (data.poll.responses.length === 0) {
          optDiv.innerHTML = '<p style="color:#9ca3af;">No answers yet</p>';
        } else {
          data.poll.responses.forEach(text => {
            const div = document.createElement('div');
            div.className = 'response-item';
            div.textContent = text;
            optDiv.appendChild(div);
          });
        }
      } else {
        const total = data.poll.options.reduce((sum, o) => sum + parseInt(o.votes), 0) || 1;
        data.poll.options.forEach(o => {
          const pct = Math.round((o.votes / total) * 100);
          const wrap = document.createElement('div');
          wrap.className = 'poll-option';
          wrap.innerHTML = `
            <div class="poll-label"><span>${escapeHtml(o.label)}</span><span>${pct}% (${o.votes})</span></div>
            <div class="bar"><div class="bar-fill" style="width:${pct}%"></div></div>`;
          optDiv.appendChild(wrap);
        });
      }
    } else {
      pollCard.style.display = 'none';
    }
  } catch (e) {
    console.error(e);
  }
}

refresh();
setInterval(refresh, 2000);
</script>

</body>
</html>
