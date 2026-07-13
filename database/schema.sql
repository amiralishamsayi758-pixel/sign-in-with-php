-- This file does not create a database.
-- The sign_in_system database must already exist before this file is executed.
-- Run this file after creating the database in MySQL Workbench. It selects the
-- existing database and creates only the project tables.

USE sign_in_system;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gmail VARCHAR(254) NOT NULL,
    phone CHAR(11) NOT NULL,
    username VARCHAR(10) NOT NULL,
    verification_code CHAR(4) NOT NULL,
    code_expires_at DATETIME NOT NULL,
    resend_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    auth_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    token_expires_at DATETIME NULL,
    last_authenticated_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_phone_unique (phone),
    KEY users_code_expires_at_index (code_expires_at),
    UNIQUE KEY users_auth_token_hash_unique (auth_token_hash),
    CONSTRAINT users_is_verified_check CHECK (is_verified IN (0, 1))
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Existing database upgrade
-- -----------------------------------------------------------------------------
-- Do not run this section for a fresh installation.
-- If the users table already exists without the three new columns, remove the
-- opening /* and closing */ markers, then run this section once.

/*
ALTER TABLE users
    ADD COLUMN code_expires_at DATETIME NULL AFTER verification_code,
    ADD COLUMN resend_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER code_expires_at,
    ADD COLUMN is_verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER resend_count;

-- Existing verification codes are treated as expired during the upgrade.
UPDATE users
SET code_expires_at = UTC_TIMESTAMP()
WHERE code_expires_at IS NULL;

ALTER TABLE users
    MODIFY COLUMN code_expires_at DATETIME NOT NULL,
    ADD KEY users_code_expires_at_index (code_expires_at),
    ADD CONSTRAINT users_is_verified_check CHECK (is_verified IN (0, 1));
*/

-- -----------------------------------------------------------------------------
-- Existing database authentication-token upgrade
-- -----------------------------------------------------------------------------
-- Run this section once only when the users table already exists and does not
-- yet contain the authentication-token columns. Existing rows are preserved.

/*
ALTER TABLE users
    ADD COLUMN auth_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER is_verified,
    ADD COLUMN token_expires_at DATETIME NULL AFTER auth_token_hash,
    ADD COLUMN last_authenticated_at DATETIME NULL AFTER token_expires_at,
    ADD UNIQUE KEY users_auth_token_hash_unique (auth_token_hash);
*/
