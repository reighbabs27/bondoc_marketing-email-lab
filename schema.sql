-- Hybrid Promotional Campaign Laboratory Schema
-- Systems Administration and Maintenance

-- Table 1: customers
CREATE TABLE IF NOT EXISTS `customers` (
    `customer_id` VARCHAR(32) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `marketing_opt_in` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`customer_id`),
    UNIQUE KEY `uk_customers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: marketing_outbox
CREATE TABLE IF NOT EXISTS `marketing_outbox` (
    `request_id` CHAR(32) NOT NULL,
    `campaign_id` VARCHAR(64) NOT NULL,
    `customer_id` VARCHAR(32) NOT NULL,
    `promo_code` VARCHAR(64) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `status` ENUM('PENDING', 'QUEUED') NOT NULL DEFAULT 'PENDING',
    `created_at` DATETIME NOT NULL,
    `queued_at` DATETIME NULL DEFAULT NULL,
    `sqs_message_id` VARCHAR(100) NULL DEFAULT NULL,
    PRIMARY KEY (`request_id`),
    UNIQUE KEY `uk_campaign_customer` (`campaign_id`, `customer_id`),
    CONSTRAINT `fk_outbox_customer` FOREIGN KEY (`customer_id`) 
        REFERENCES `customers` (`customer_id`) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: sent_marketing_emails
-- Logical customer association rather than enforced foreign key to allow recording unknown-customer requests as SKIPPED
CREATE TABLE IF NOT EXISTS `sent_marketing_emails` (
    `campaign_id` VARCHAR(64) NOT NULL,
    `customer_id` VARCHAR(32) NOT NULL,
    `request_id` VARCHAR(100) NOT NULL,
    `promo_code` VARCHAR(64) NOT NULL,
    `recipient_email` VARCHAR(254) NULL DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('SENT', 'SKIPPED', 'FAILED') NOT NULL,
    `provider` VARCHAR(50) NULL DEFAULT NULL,
    `message_id` VARCHAR(255) NULL DEFAULT NULL,
    `reason` TEXT NULL DEFAULT NULL,
    `processed_at` DATETIME NOT NULL,
    `sent_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`campaign_id`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
