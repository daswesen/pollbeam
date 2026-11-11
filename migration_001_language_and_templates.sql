-- Adds a per-session UI language (German/English) and reusable
-- question templates to an existing installation, without touching
-- existing data. Run this once via phpMyAdmin (or the mysql CLI)
-- against your existing database if you installed PollBeam before
-- this version.

ALTER TABLE sessions
  ADD COLUMN language ENUM('de', 'en') NOT NULL DEFAULT 'en' AFTER title;

CREATE TABLE IF NOT EXISTS poll_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    question VARCHAR(500) NOT NULL,
    type ENUM('choice', 'open') NOT NULL DEFAULT 'choice',
    options_text TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
