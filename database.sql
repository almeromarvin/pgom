-- PGOM FACILITIES BOOKING & INVENTORY SYSTEM
-- Clean Database Schema - Fixed Foreign Key Constraints

CREATE DATABASE pgom_facilities; 
USE pgom_facilities;

-- Drop existing tables if they exist (in reverse dependency order)
DROP TABLE IF EXISTS `inventory_history`;
DROP TABLE IF EXISTS `booking_equipment`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `user_history`;
DROP TABLE IF EXISTS `equipment_items`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `equipment_groups`;
DROP TABLE IF EXISTS `facilities`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `password_reset_tokens`;

-- 1. USERS TABLE (Base table - no dependencies)
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `email` VARCHAR(255) NULL,
    `name` VARCHAR(255) NULL,
    `suffix` VARCHAR(50) NULL,
    `birthday` DATE NULL,
    `gender` VARCHAR(20) NULL,
    `phone_number` VARCHAR(20) NULL,
    `position` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `valid_id_type` VARCHAR(50) NULL,
    `valid_id_number` VARCHAR(50) NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. EQUIPMENT GROUPS TABLE (No dependencies)
CREATE TABLE `equipment_groups` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. FACILITIES TABLE (No dependencies)
CREATE TABLE `facilities` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `status` ENUM('Available','Maintenance','Reserved') NOT NULL DEFAULT 'Available',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. NOTIFICATIONS TABLE (No dependencies)
CREATE TABLE `notifications` (
    `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'info',
    `link` VARCHAR(255),
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`is_read`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. INVENTORY TABLE (Depends on equipment_groups)
CREATE TABLE `inventory` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `total_quantity` INT(11) NOT NULL DEFAULT 0,
    `borrowed` INT(11) NOT NULL DEFAULT 0,
    `minimum_stock` INT(11) NOT NULL DEFAULT 10,
    `status` ENUM('Available','In Maintenance','Low Stock') NOT NULL DEFAULT 'Available',
    `description` TEXT NULL,
    `group_id` INT(11) NULL,
    `is_group` TINYINT(1) NOT NULL DEFAULT 0,
    `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`group_id`) REFERENCES `equipment_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. INVENTORY HISTORY TABLE (Depends on inventory and users)
CREATE TABLE `inventory_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `item_id` INT(11) NOT NULL,
    `action` TEXT NOT NULL,
    `quantity` INT(11) NOT NULL,
    `modified_by` INT(11) NOT NULL,
    `date_modified` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `item_id` (`item_id`),
    KEY `modified_by` (`modified_by`),
    CONSTRAINT `inventory_history_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE,
    CONSTRAINT `inventory_history_ibfk_2` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BOOKINGS TABLE (Depends on facilities and users)
CREATE TABLE `bookings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `facility_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `request_letter` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `facility_id` (`facility_id`),
    KEY `user_id` (`user_id`),
    KEY `idx_booking_dates` (`start_time`, `end_time`),
    KEY `idx_booking_status` (`status`),
    CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BOOKING EQUIPMENT TABLE (Depends on bookings and inventory)
CREATE TABLE `booking_equipment` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) NOT NULL,
    `equipment_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `booking_id` (`booking_id`),
    KEY `equipment_id` (`equipment_id`),
    CONSTRAINT `booking_equipment_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `booking_equipment_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. USER HISTORY TABLE (Depends on users)
CREATE TABLE `user_history` (
    `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `admin_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `facility_name` VARCHAR(255) NULL,
    `start_time` DATETIME NULL,
    `end_time` DATETIME NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. EQUIPMENT ITEMS TABLE (Depends on equipment_groups)
CREATE TABLE `equipment_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`group_id`) REFERENCES `equipment_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. PASSWORD RESET TOKENS TABLE (Depends on users)
CREATE TABLE `password_reset_tokens` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(6) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `email` (`email`),
    KEY `token` (`token`),
    KEY `expires_at` (`expires_at`),
    KEY `idx_active_tokens` (`email`, `used`, `expires_at`),
    CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADD INDEXES FOR BETTER PERFORMANCE
CREATE INDEX `idx_user_history_user_id` ON `user_history`(`user_id`);
CREATE INDEX `idx_user_history_admin_id` ON `user_history`(`admin_id`);
CREATE INDEX `idx_inventory_group_id` ON `inventory`(`group_id`);
CREATE INDEX `idx_inventory_status` ON `inventory`(`status`);
CREATE INDEX `idx_bookings_user_status` ON `bookings`(`user_id`, `status`);

-- INSERT DEFAULT DATA

-- Insert default admin account (Password: admin123)
INSERT INTO `users` (`username`, `password`, `role`, `name`, `email`) VALUES 
('admin', '$2y$10$8X/0kGpHyZxUHUFBX.b5B.x5eqpFUE1bGLKvVJe7UcfD7xQAXzjfu', 'admin', 'System Administrator', 'admin@pgom.com');

-- Insert default user account (Password: user123)
INSERT INTO `users` (`username`, `password`, `role`, `name`, `email`) VALUES 
('user', '$2y$10$YFVRwvVwJQFxe4qxAK.YZeJNwZ3l/0MXK3AYgk4x.P8ZBgUP5eKYS', 'user', 'Test User', 'user@pgom.com');

-- Insert default equipment groups
INSERT INTO `equipment_groups` (`name`, `description`) VALUES
('Sound System', 'Complete sound system package including all necessary equipment'),
('Lighting', 'Event lighting equipment'),
('Furniture', 'Event furniture and seating');

-- Insert default facilities
INSERT INTO `facilities` (`name`, `description`, `status`) VALUES
('Training Center', 'Large hall for major events and training sessions', 'Available'),
('Evacuation Center', 'Medium-sized room for meetings and emergency purposes', 'Available'),
('Grand Plaza', 'Equipped room for training sessions and small events', 'Available'),
('Event Center', 'Medium-sized room for meetings and events', 'Available'),
('Back Door', 'Small meeting room for intimate gatherings', 'Available');

-- Insert default inventory items
INSERT INTO `inventory` (`name`, `total_quantity`, `borrowed`, `minimum_stock`, `status`, `description`, `is_group`) VALUES
('Chairs', 1000, 0, 50, 'Available', 'Standard plastic chairs for events', 0),
('Tables', 500, 0, 25, 'Available', 'Folding tables for various uses', 0),
('Industrial Fan', 50, 0, 5, 'Available', 'Industrial cooling fans', 0),
('Extension Wires', 100, 0, 10, 'Available', 'Extension cords and power strips', 0),
('Red Carpet', 10, 0, 2, 'Available', 'Event red carpet', 0),
('Podium', 5, 0, 1, 'Available', 'Speaker podium', 0);

-- Insert sound system group item
INSERT INTO `inventory` (`name`, `total_quantity`, `borrowed`, `minimum_stock`, `status`, `description`, `group_id`, `is_group`) VALUES
('Sound System', 5, 0, 1, 'Available', 'Complete sound system package', 1, 1);

-- Insert sound system components
INSERT INTO `inventory` (`name`, `total_quantity`, `borrowed`, `minimum_stock`, `status`, `description`, `group_id`, `is_group`) VALUES
('Microphone', 10, 0, 2, 'Available', 'Wireless microphone', 1, 0),
('Speaker', 10, 0, 2, 'Available', 'Main speaker unit', 1, 0),
('Mixer', 5, 0, 1, 'Available', 'Audio mixer', 1, 0),
('Amplifier', 5, 0, 1, 'Available', 'Power amplifier', 1, 0),
('Cables', 20, 0, 5, 'Available', 'Audio cables and connectors', 1, 0);

-- Insert equipment items for groups
INSERT INTO `equipment_items` (`group_id`, `name`, `description`) VALUES
(1, 'Microphone', 'Wireless microphone'),
(1, 'Speaker', 'Main speaker unit'),
(1, 'Mixer', 'Audio mixer'),
(1, 'Amplifier', 'Power amplifier'),
(1, 'Cables', 'Audio cables and connectors'),
(2, 'Spotlight', 'Event spotlight'),
(2, 'LED Panel', 'LED lighting panel'),
(3, 'Chair', 'Event chair'),
(3, 'Table', 'Event table'); 