-- PollBeam database schema
-- Import e.g. via phpMyAdmin

CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(255) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question VARCHAR(500) NOT NULL,
    type ENUM('choice', 'open') NOT NULL DEFAULT 'choice',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    votes INT DEFAULT 0,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

-- Free-text answers for polls of type 'open'
CREATE TABLE IF NOT EXISTS poll_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

-- Example session for testing
INSERT INTO sessions (code, title) VALUES ('demo', 'Test Session');
