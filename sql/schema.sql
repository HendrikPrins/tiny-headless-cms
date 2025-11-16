CREATE TABLE users
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    role         VARCHAR(20) NOT NULL DEFAULT 'editor'
);

CREATE TABLE content_types
(
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL UNIQUE,
    is_singleton BOOLEAN DEFAULT FALSE
);

CREATE TABLE entries
(
    id              INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id INT NOT NULL,
    FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE CASCADE
);

CREATE TABLE fields
(
    id              INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id INT          NOT NULL,
    name            VARCHAR(255) NOT NULL,
    field_type      VARCHAR(30) NOT NULL,
    is_required     BOOLEAN DEFAULT FALSE,
    is_translatable BOOLEAN DEFAULT FALSE,
    `order`         INT     DEFAULT 0,
    FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE CASCADE
);

CREATE TABLE field_values
(
    entry_id INT NOT NULL,
    field_id INT NOT NULL,
    locale   VARCHAR(10) DEFAULT NULL,
    value    LONGTEXT,
    FOREIGN KEY (entry_id) REFERENCES entries (id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES fields (id) ON DELETE CASCADE,
    PRIMARY KEY unique_field_locale (entry_id, field_id, locale)
);

CREATE INDEX idx_entries_content_type ON entries (content_type_id);
CREATE INDEX idx_field_values_entry_field ON field_values (entry_id, field_id);
CREATE INDEX idx_field_values_locale ON field_values (locale);
