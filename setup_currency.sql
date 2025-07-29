-- Create user_settings_tbl if it doesn't exist
CREATE TABLE IF NOT EXISTS `user_settings_tbl` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_setting` (`user_id`, `setting_key`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users_tbl` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default PKR currency setting for all existing users
INSERT IGNORE INTO `user_settings_tbl` (`user_id`, `setting_key`, `setting_value`)
SELECT `id`, 'currency', 'PKR' FROM `users_tbl`;

-- Insert default notification settings for all existing users  
INSERT IGNORE INTO `user_settings_tbl` (`user_id`, `setting_key`, `setting_value`)
SELECT `id`, 'email_notifications', '1' FROM `users_tbl`;

INSERT IGNORE INTO `user_settings_tbl` (`user_id`, `setting_key`, `setting_value`)
SELECT `id`, 'expiry_alert_days', '30' FROM `users_tbl`;