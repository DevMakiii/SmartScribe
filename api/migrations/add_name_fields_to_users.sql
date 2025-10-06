-- Migration to add first_name and last_name columns to users table
-- Run this script to update your database schema

-- Add first_name and last_name columns
ALTER TABLE users
ADD COLUMN first_name VARCHAR(255) AFTER id,
ADD COLUMN last_name VARCHAR(255) AFTER first_name;

-- Update existing records to split the name field
-- This will split names like "John Doe" into first_name="John" and last_name="Doe"
UPDATE users SET
first_name = SUBSTRING_INDEX(name, ' ', 1),
last_name = TRIM(SUBSTRING(name, LOCATE(' ', name) + 1))
WHERE name LIKE '% %';

-- For names without spaces, put the full name in first_name and leave last_name empty
UPDATE users SET
first_name = name,
last_name = ''
WHERE first_name IS NULL;

-- Make the columns NOT NULL after populating data
ALTER TABLE users
MODIFY COLUMN first_name VARCHAR(255) NOT NULL,
MODIFY COLUMN last_name VARCHAR(255) NOT NULL;

-- Optional: You can keep the original name column or drop it
-- If you want to keep it for backward compatibility, leave it as is
-- If you want to remove it, uncomment the line below:
-- ALTER TABLE users DROP COLUMN name;