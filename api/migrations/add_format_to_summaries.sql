-- Add format field to summaries table for bullet point support
ALTER TABLE summaries
ADD COLUMN format ENUM('paragraph','bullet_points') DEFAULT 'paragraph' AFTER length;

-- Update existing records to have paragraph format (for backward compatibility)
UPDATE summaries SET format = 'paragraph' WHERE format IS NULL;