-- Update quizzes table to add missing columns required by the Quiz model
-- This migration adds the columns that were missing from the original table creation

-- Add user_id column
ALTER TABLE `quizzes` ADD COLUMN `user_id` int NOT NULL AFTER `note_id`;

-- Add title column
ALTER TABLE `quizzes` ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `score`;

-- Add note_title column
ALTER TABLE `quizzes` ADD COLUMN `note_title` varchar(255) DEFAULT NULL AFTER `title`;

-- Add total_questions column
ALTER TABLE `quizzes` ADD COLUMN `total_questions` int DEFAULT 0 AFTER `note_title`;

-- Add foreign key constraint for user_id
ALTER TABLE `quizzes` ADD CONSTRAINT `quizzes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Add index for user_id
ALTER TABLE `quizzes` ADD KEY `user_id` (`user_id`);