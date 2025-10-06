-- Add google_id column to users table for Google OAuth integration
ALTER TABLE users ADD COLUMN google_id VARCHAR(255) UNIQUE DEFAULT NULL AFTER email;
-- Add index for faster lookups
ALTER TABLE users ADD INDEX idx_google_id (google_id);