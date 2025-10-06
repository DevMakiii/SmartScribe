-- Add Google OAuth fields to users table
ALTER TABLE `users` ADD COLUMN `google_id` varchar(255) UNIQUE DEFAULT NULL AFTER `email`;
ALTER TABLE `users` ADD COLUMN `auth_provider` enum('local','google') DEFAULT 'local' AFTER `google_id`;
ALTER TABLE `users` ADD COLUMN `profile_picture_url` varchar(500) DEFAULT NULL AFTER `profile_picture`;