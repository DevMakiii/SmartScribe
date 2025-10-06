-- Add quiz_type column to quizzes table
ALTER TABLE `quizzes` ADD COLUMN `quiz_type` enum('multiple_choice','true_false','mixed') DEFAULT 'multiple_choice' AFTER `difficulty`;