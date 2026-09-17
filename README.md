# PollBeam

A very simple, self-hosted live polling tool -- a lightweight alternative to Slido/Mentimeter for your own web space. Runs on ordinary PHP/MySQL shared hosting, no Node.js, WebSockets, or build step required. The browser updates via polling (every 2 seconds), which is plenty for lectures, talks, and workshops.

Two poll types:
- **Choice answers** -- classic multiple-choice poll with a live bar chart
- **Free text** -- participants type their own answers, which stream in live as a list (e.g. "What did you learn today?")

Multiple submissions per participant are allowed by design -- there's no lock against voting more than once.

## Why

For occasional use in a lecture or talk, a very simple tool is enough. If you already have web space, there's no need to subscribe to another cloud service or set up Node.js for it.

## Requirements

- PHP 7.4+ with the PDO/MySQL extension
- A MySQL database
- Apache with `mod_rewrite` (for the short participant links -- optional, see below)

Tested on ordinary netcup/Plesk shared hosting, but should run on any classic PHP web space.

## Installation

1. Clone the repository or upload the files to a folder on your web space via FTP.
2. Create a MySQL database and import `schema.sql` (e.g. via phpMyAdmin).
3. Copy `config.example.php` to `config.php` and fill in your own credentials:
   ```php
   $DB_HOST = 'localhost';
   $DB_NAME = 'your_database';
   $DB_USER = 'your_username';
   $DB_PASS = 'your_password';
   $MODERATOR_PASSWORD = 'please-change-me';
   ```
   `config.php` is listed in `.gitignore` and will never be accidentally uploaded/committed.
4. Done. The moderator interface lives at `moderate.php`.

### Short participant links (optional but recommended)

Without further configuration, links work as `index.php?code=YOURCODE`. With `mod_rewrite` (the included `.htaccess` handles this automatically), these become short links:

```
https://your-domain.com/EF            -> participant view
https://your-domain.com/EF/presenter  -> presenter view (for the projector)
```

If your hosting runs Apache behind an nginx proxy (common with Plesk hosting), you may need to disable the "serve static files directly by nginx" option there and allow `AllowOverride All`, or the `.htaccess` won't take effect at all.

## Usage

1. Open **`moderate.php`** and log in with your `$MODERATOR_PASSWORD`.
2. Create a **session** (short code, no spaces or special characters, e.g. `talk2026`).
3. Share the participant link or QR code.
4. Create a **poll** -- choice answers or free text. The most recently created poll in a session automatically becomes the active one.
5. Put the **presenter link** on the projector/screen -- shows live results including a QR code to scan.
6. Results for every poll can be downloaded as timestamped CSV under "Past polls".

## Files

| File | Purpose |
|---|---|
| `schema.sql` | Database structure (import once) |
| `config.example.php` | Template for `config.php` |
| `moderate.php` | Admin: create/delete sessions and polls, export results |
| `index.php` | Participant view |
| `presenter.php` | View for projector/large screens, incl. QR code |
| `poll.php` | Returns the current state as JSON (polled by the browser) |
| `submit.php` | Accepts poll answers |
| `export.php` | CSV export of a poll |
| `.htaccess` | Rewrite rules for short links |

## Limitations

This is deliberately a very simple tool, not a product:

- No user account system, just a single shared moderator password
- No protection against multiple votes (intentional, see above)
- Polling instead of WebSockets -- for very large numbers of simultaneous participants (well over a few hundred), the interval would need adjusting or caching added
- No automatic HTTPS -- should be ensured via your hosting settings (e.g. Let's Encrypt)

Pull requests and issues welcome.

## License

MIT, see [LICENSE](LICENSE).
