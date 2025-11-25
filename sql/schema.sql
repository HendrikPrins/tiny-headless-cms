CREATE TABLE users
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    role         VARCHAR(20) NOT NULL DEFAULT 'editor'
);

CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    directory VARCHAR(500) DEFAULT '',
    mime_type VARCHAR(100),
    size BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
