-- Add note_title column to quizzes table
ALTER TABLE `quizzes` ADD COLUMN `note_title` varchar(255) DEFAULT NULL AFTER `score`;