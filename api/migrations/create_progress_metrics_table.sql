-- Create progress_metrics table for detailed learning metrics over time
CREATE TABLE IF NOT EXISTS `progress_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `metric_date` date NOT NULL,
  `total_notes` int DEFAULT '0',
  `total_quizzes` int DEFAULT '0',
  `total_study_time` int DEFAULT '0' COMMENT 'Total study time in minutes',
  `average_accuracy` decimal(5,2) DEFAULT NULL,
  `study_streak` int DEFAULT '0',
  `subjects_mastered` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `metric_date` (`metric_date`),
  CONSTRAINT `progress_metrics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;