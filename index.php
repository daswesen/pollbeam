<?php $serverCode = $_GET['code'] ?? ''; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PollBeam</title>
<style>
  body { font-family: -apple-system, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f7f7f8; }
  h1 { font-size: 1.3em; }
  .card { background: white; border-radius: 10px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
  .poll-badge { display: inline-block; background: #dbeafe; color: #1d4ed8; font-size: 0.75em; font-weight: bold; letter-spacing: 0.05em; padding: 3px 8px; border-radius: 4px; margin-bottom: 8px; }
  textarea, input[type=text] { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em; }
  button { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 6px; font-size: 1em; cursor: pointer; margin-top: 8px; }
  button:hover { background: #1d4ed8; }
  .poll-option { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; }
  .poll-option button { margin-top: 0; }
  .bar { background: #e5e7eb; border-radius: 4px; height: 8px; margin-top: 4px; overflow: hidden; }
  .bar-fill { background: #2563eb; height: 100%; }
  .msg { color: #16a34a; font-size: 0.9em; margin-top: 6px; }
</style>
</head>
<body>

<h1 id="session-title">Loading session…</h1>

<div class="card" id="poll-card" style="display:none;">
  <div class="poll-badge">POLL</div>
  <strong id="poll-question"></strong>
  <div id="poll-options"></div>
</div>

<script>
// Determined server-side (works both for /EF and ?code=EF),
// with a fallback to the client-side query parameter and finally 'demo'.
const params = new URLSearchParams(window.location.search);
const code = <?= json_encode($serverCode) ?> || params.get('code') || 'demo';
let currentOpenPollId = null;

async function refresh() {
  try {
    const res = await fetch('/poll.php?code=' + encodeURIComponent(code));
    const data = await res.json();
    if (!data.ok) {
      document.getElementById('session-title').textContent = 'Error: ' + data.error;
      return;
    }
    document.getElementById('session-title').textContent = data.title || 'Live Session';

    // Poll
    const pollCard = document.getElementById('poll-card');
    if (data.poll) {
      pollCard.style.display = 'block';
      document.getElementById('poll-question').textContent = data.poll.question;
      const optDiv = document.getElementById('poll-options');

      if (data.poll.type === 'open') {
        // Don't rebuild the field while the same poll is still active,
        // otherwise typed text would be lost on refresh.
        if (currentOpenPollId !== data.poll.id) {
          currentOpenPollId = data.poll.id;
          optDiv.innerHTML = `
            <textarea id="poll-answer-text" rows="2" placeholder="Your answer..." style="margin-top:8px;"></textarea>
            <button onclick="pollAnswer(${data.poll.id})">Submit</button>
            <div class="msg" id="poll-answer-msg"></div>`;
        }
      } else {
        currentOpenPollId = null;
        optDiv.innerHTML = '';
        const total = data.poll.options.reduce((sum, o) => sum + parseInt(o.votes), 0) || 1;
        data.poll.options.forEach(o => {
          const pct = Math.round((o.votes / total) * 100);
          const wrap = document.createElement('div');
          wrap.className = 'poll-option';
          wrap.innerHTML = `
            <div style="flex:1;">
              <div>${escapeHtml(o.label)} (${o.votes})</div>
              <div class="bar"><div class="bar-fill" style="width:${pct}%"></div></div>
            </div>
            <button onclick="pollVote(${o.id})">Vote</button>`;
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

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

async function pollAnswer(pollId) {
  const field = document.getElementById('poll-answer-text');
  const text = field.value.trim();
  if (!text) return;
  await fetch('/submit.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=poll_answer&poll_id=${pollId}&text=${encodeURIComponent(text)}`
  });
  field.value = '';
  const msg = document.getElementById('poll-answer-msg');
  if (msg) {
    msg.textContent = 'Thanks for your answer!';
    setTimeout(() => { if (msg) msg.textContent = ''; }, 2000);
  }
}

async function pollVote(optionId) {
  await fetch('/submit.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=poll_vote&option_id=${optionId}`
  });
  refresh();
}

refresh();
setInterval(refresh, 2000);
</script>

</body>
</html>
