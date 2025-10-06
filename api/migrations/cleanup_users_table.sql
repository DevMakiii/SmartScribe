-- Migration to clean up and optimize users table structure
-- This migration ensures data consistency between name fields and adds proper constraints

-- First, ensure all existing users have proper first_name and last_name values
-- For users where first_name is NULL or empty, populate from the name field
UPDATE users SET
    first_name = SUBSTRING_INDEX(name, ' ', 1),
    last_name = TRIM(SUBSTRING(name, LOCATE(' ', name) + 1))
WHERE (first_name IS NULL OR first_name = '') AND name LIKE '% %';

-- For users with single names, put the full name in first_name and leave last_name empty
UPDATE users SET
    first_name = name,
    last_name = ''
WHERE (first_name IS NULL OR first_name = '') AND (name NOT LIKE '% %' OR name IS NOT NULL);

-- Ensure name field is in sync with first_name and last_name
UPDATE users SET
    name = TRIM(CONCAT(first_name, ' ', last_name))
WHERE first_name IS NOT NULL AND last_name IS NOT NULL;

-- Make first_name and last_name NOT NULL with default empty strings
ALTER TABLE users
MODIFY COLUMN first_name VARCHAR(255) NOT NULL DEFAULT '',
MODIFY COLUMN last_name VARCHAR(255) NOT NULL DEFAULT '';

-- Add a comment to explain the name field redundancy
ALTER TABLE users
MODIFY COLUMN name VARCHAR(255) NOT NULL COMMENT 'Combined name field for backward compatibility - kept in sync with first_name + last_name';

-- Add check constraint to ensure name is never empty
ALTER TABLE users
ADD CONSTRAINT chk_name_not_empty CHECK (name != '');

-- Optimize indexes
DROP INDEX IF EXISTS idx_email ON users;
DROP INDEX IF EXISTS idx_created_at ON users;
CREATE INDEX idx_email ON users(email);
CREATE INDEX idx_created_at ON users(created_at);
CREATE INDEX idx_name ON users(name);