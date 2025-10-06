-- Add keywords column to notes table for auto-extracted keywords
ALTER TABLE notes ADD COLUMN keywords TEXT DEFAULT NULL COMMENT 'Comma-separated list of auto-extracted keywords';