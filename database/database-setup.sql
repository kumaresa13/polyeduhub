-- PolyEduHub Database Setup
-- This script creates all necessary tables for the PolyEduHub platform

-- Create database (uncomment if needed)
-- CREATE DATABASE polyeduhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE polyeduhub;

-- Users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('student', 'admin') NOT NULL DEFAULT 'student',
  `department` varchar(100) NULL,
  `student_id` varchar(20) NULL,
  `year_of_study` int(1) NULL,
  `profile_image` varchar(255) NULL,
  `bio` text NULL,
  `status` enum('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resources table
CREATE TABLE `resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `thumbnail` varchar(255) NULL,
  `category_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `download_count` int(11) NOT NULL DEFAULT 0,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resource categories
CREATE TABLE `resource_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text NULL,
  `parent_id` int(11) NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resource tags
CREATE TABLE `resource_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resource-tag relationship (many-to-many)
CREATE TABLE `resource_tag_relationship` (
  `resource_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`resource_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resource ratings
CREATE TABLE `resource_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_resource_rating` (`user_id`, `resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resource comments
CREATE TABLE `resource_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat rooms
CREATE TABLE `chat_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text NULL,
  `type` enum('public', 'private', 'group') NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat room members
CREATE TABLE `chat_room_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_user` (`room_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat messages
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User points (for gamification)
CREATE TABLE `user_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `level` int(11) NOT NULL DEFAULT 1,
  `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Points history
CREATE TABLE `points_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Badges
CREATE TABLE `badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `icon` varchar(255) NOT NULL,
  `required_points` int(11) NULL,
  `required_action` varchar(100) NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User badges
CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `awarded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_badge` (`user_id`, `badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text NULL,
  `ip_address` varchar(45) NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset tokens
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remember Me tokens
CREATE TABLE `remember_me_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraints
ALTER TABLE `resources` ADD CONSTRAINT `fk_resources_category` FOREIGN KEY (`category_id`) REFERENCES `resource_categories` (`id`);
ALTER TABLE `resources` ADD CONSTRAINT `fk_resources_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `resource_categories` ADD CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `resource_categories` (`id`);
ALTER TABLE `resource_tag_relationship` ADD CONSTRAINT `fk_tag_relationship_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;
ALTER TABLE `resource_tag_relationship` ADD CONSTRAINT `fk_tag_relationship_tag` FOREIGN KEY (`tag_id`) REFERENCES `resource_tags` (`id`) ON DELETE CASCADE;
ALTER TABLE `resource_ratings` ADD CONSTRAINT `fk_ratings_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;
ALTER TABLE `resource_ratings` ADD CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `resource_comments` ADD CONSTRAINT `fk_comments_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;
ALTER TABLE `resource_comments` ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `chat_rooms` ADD CONSTRAINT `fk_chatroom_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
ALTER TABLE `chat_room_members` ADD CONSTRAINT `fk_chatmember_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE;
ALTER TABLE `chat_room_members` ADD CONSTRAINT `fk_chatmember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `chat_messages` ADD CONSTRAINT `fk_chatmessage_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE;
ALTER TABLE `chat_messages` ADD CONSTRAINT `fk_chatmessage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `user_points` ADD CONSTRAINT `fk_points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `points_history` ADD CONSTRAINT `fk_pointshistory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `user_badges` ADD CONSTRAINT `fk_userbadge_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `user_badges` ADD CONSTRAINT `fk_userbadge_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`);
ALTER TABLE `activity_log` ADD CONSTRAINT `fk_activitylog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `password_reset_tokens` ADD CONSTRAINT `fk_passwordreset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `remember_me_tokens` ADD CONSTRAINT `fk_rememberme_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Insert default data
-- Default admin user (password: admin123)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `role`) 
VALUES ('Admin', 'User', 'admin@polyeduhub.com', '$2y$10$JsYDtF8evTbz2KbCQpfnNuY/8YQqFDYXYR0bA/MR8YhMNXK3mQk.W', 'admin');

-- Default resource categories
INSERT INTO `resource_categories` (`name`, `description`) VALUES 
('Notes', 'Study notes and lecture materials'),
('Assignments', 'Assignment materials and examples'),
('Activities', 'Learning activities and exercises'),
('Projects', 'Project documentation and reports'),
('Exams', 'Past year exam papers and solutions'),
('Tutorials', 'Tutorial materials and guides');

-- Default badges
INSERT INTO `badges` (`name`, `description`, `icon`, `required_points`) VALUES
('Newcomer', 'Joined the platform', 'badge-newcomer.png', 0),
('Resource Contributor', 'Uploaded 5 resources', 'badge-contributor.png', 50),
('Active Participant', 'Earned 100 points', 'badge-participant.png', 100),
('Knowledge Sharer', 'Shared 10 resources with high ratings', 'badge-sharer.png', 200),
('Community Helper', 'Helped others through comments and ratings', 'badge-helper.png', 300),
('Education Champion', 'Contributed significantly to the platform', 'badge-champion.png', 500);

-- Grant default badges to admin
INSERT INTO `user_points` (`user_id`, `points`, `level`) VALUES (1, 1000, 5);
INSERT INTO `user_badges` (`user_id`, `badge_id`) VALUES (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6);