-- Create study_sessions table for tracking actual study time and activities
CREATE TABLE IF NOT EXISTS `study_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration_minutes` int NOT NULL,
  `activities` json DEFAULT NULL COMMENT 'JSON array of activities performed',
  `notes_studied` int DEFAULT '0',
  `quizzes_taken` int DEFAULT '0',
  `average_score` decimal(5,2) DEFAULT NULL,
  `focus_level` enum('low','medium','high') DEFAULT 'medium',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_date` (`session_date`),
  CONSTRAINT `study_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;