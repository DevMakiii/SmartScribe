-- Migration to add profile_picture column to users table
-- Run this script to update your database schema

-- Add profile_picture column
ALTER TABLE users
ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER last_name;

-- Optional: Add a comment to the column
ALTER TABLE users
MODIFY COLUMN profile_picture VARCHAR(255) DEFAULT NULL COMMENT 'Path to user profile picture';