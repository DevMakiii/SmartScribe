-- Add format field to notes table for summary format preference
ALTER TABLE notes
ADD COLUMN summary_format ENUM('paragraph','bullet_points') DEFAULT 'paragraph' AFTER original_text;

-- Update existing records to have paragraph format (for backward compatibility)
UPDATE notes SET summary_format = 'paragraph' WHERE summary_format IS NULL;