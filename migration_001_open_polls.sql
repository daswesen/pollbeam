-- Nachträgliche Änderung für bereits angelegte Datenbanken:
-- fügt Freitext-Umfragen hinzu, ohne bestehende Daten zu löschen.
-- Einmalig per phpMyAdmin auf der bestehenden Datenbank ausführen.

ALTER TABLE polls
  ADD COLUMN type ENUM('choice', 'open') NOT NULL DEFAULT 'choice' AFTER question;

CREATE TABLE IF NOT EXISTS poll_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);
