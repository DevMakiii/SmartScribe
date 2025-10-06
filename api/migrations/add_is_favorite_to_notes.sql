-- Add is_favorite column to notes table
ALTER TABLE notes
ADD COLUMN is_favorite BOOLEAN DEFAULT FALSE AFTER image_path,
ADD INDEX idx_is_favorite (is_favorite);