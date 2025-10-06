-- Add title column to quizzes table
ALTER TABLE `quizzes` ADD COLUMN `title` varchar(255) DEFAULT NULL AFTER `score`;