# PollBeam

Ein sehr einfaches, selbstgehostetes Live-Umfrage-Tool -- eine schlanke Alternative zu Slido/Mentimeter für den eigenen Webspace. Läuft auf gewöhnlichem PHP/MySQL-Shared-Hosting, ganz ohne Node.js, WebSockets oder Build-Prozess. Die Aktualisierung im Browser läuft per Polling (alle 2 Sekunden), das reicht für Vorlesungen, Vorträge und Workshops locker aus.

Zwei Umfragetypen:
- **Auswahlantworten** -- klassische Multiple-Choice-Umfrage mit Live-Balkendiagramm
- **Freie Texteingabe** -- Teilnehmer schreiben eigene Antworten, die live als Liste einlaufen (z. B. "Was habt ihr heute gelernt?")

Mehrfachantworten sind bewusst erlaubt -- es gibt keine Sperre gegen mehrfaches Abstimmen.

## Warum

Für den gelegentlichen Einsatz in Vorlesung oder Vortrag reicht ein sehr einfaches Werkzeug. Wer ohnehin Webspace hat, muss dafür keinen weiteren Cloud-Dienst abonnieren oder Node.js aufsetzen.

## Voraussetzungen

- PHP 7.4+ mit PDO/MySQL-Erweiterung
- Eine MySQL-Datenbank
- Apache mit `mod_rewrite` (für die kurzen Teilnehmer-Links -- optional, siehe unten)

Getestet auf gewöhnlichem netcup-/Plesk-Shared-Hosting, sollte aber auf jedem klassischen PHP-Webspace laufen.

## Installation

1. Repository klonen bzw. Dateien per FTP in einen Ordner deines Webspace hochladen.
2. Eine MySQL-Datenbank anlegen und `schema.sql` importieren (z. B. per phpMyAdmin).
3. `config.example.php` nach `config.php` kopieren und die eigenen Zugangsdaten eintragen:
   ```php
   $DB_HOST = 'localhost';
   $DB_NAME = 'deine_datenbank';
   $DB_USER = 'dein_benutzername';
   $DB_PASS = 'dein_passwort';
   $MODERATOR_PASSWORD = 'bitte-aendern';
   ```
   `config.php` ist in `.gitignore` eingetragen und wird nie versehentlich mit hochgeladen/committet.
4. Fertig. Die Moderationsoberfläche liegt unter `moderate.php`.

### Kurze Teilnehmer-Links (optional, aber empfohlen)

Ohne weitere Konfiguration funktionieren die Links als `index.php?code=DEINCODE`. Mit `mod_rewrite` (die mitgelieferte `.htaccess` macht das automatisch) werden daraus kurze Links:

```
https://deine-domain.de/EF            -> Teilnehmer-Ansicht
https://deine-domain.de/EF/presenter  -> Presenter-Ansicht (für den Beamer)
```

Falls dein Hosting Apache hinter einem nginx-Proxy betreibt (häufig bei Plesk-Hosting), muss dort ggf. die Option "Statische Dateien direkt durch nginx bedienen" **deaktiviert** und `AllowOverride All` erlaubt sein, damit die `.htaccess` überhaupt greift.

## Nutzung

1. **`moderate.php`** aufrufen, mit dem `$MODERATOR_PASSWORD` einloggen.
2. Eine **Session** anlegen (kurzer Code ohne Leerzeichen/Umlaute, z. B. `vortrag2026`).
3. Den Teilnehmer-Link bzw. QR-Code teilen.
4. Eine **Umfrage** anlegen -- Auswahlantworten oder Freitext. Die zuletzt angelegte Umfrage einer Session ist automatisch die aktive.
5. **Presenter-Link** auf den Beamer/die Leinwand -- zeigt Live-Ergebnisse inklusive QR-Code zum Scannen.
6. Ergebnisse jeder Umfrage lassen sich unter "Bisherige Umfragen" als CSV mit Zeitstempeln herunterladen.

## Dateien

| Datei | Zweck |
|---|---|
| `schema.sql` | Datenbankstruktur (einmalig importieren) |
| `migration_*.sql` | Optionale Migrationen für bereits bestehende Installationen |
| `config.example.php` | Vorlage für `config.php` |
| `moderate.php` | Verwaltung: Sessions/Umfragen anlegen, löschen, Ergebnisse exportieren |
| `index.php` | Teilnehmer-Ansicht |
| `presenter.php` | Ansicht für Beamer/große Bildschirme inkl. QR-Code |
| `poll.php` | Liefert den aktuellen Stand als JSON (wird per Polling abgefragt) |
| `submit.php` | Nimmt Umfrage-Antworten entgegen |
| `export.php` | CSV-Export einer Umfrage |
| `.htaccess` | Rewrite-Regeln für kurze Links |

## Grenzen

Das ist bewusst ein sehr einfaches Werkzeug, kein Produkt:

- Kein Nutzerkonto-System, nur ein geteiltes Moderationspasswort
- Kein Schutz gegen Mehrfachabstimmen (Absicht, siehe oben)
- Polling statt WebSockets -- bei sehr vielen gleichzeitigen Teilnehmern (deutlich über einige hundert) müsste das Intervall angepasst oder ein Caching ergänzt werden
- Kein automatisches HTTPS -- sollte über die Hosting-Einstellungen (z. B. Let's Encrypt) sichergestellt werden

Pull Requests und Issues willkommen.

## Lizenz

MIT, siehe [LICENSE](LICENSE).
