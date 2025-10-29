-- Ten plik zawiera schemat bazy danych dla aplikacji do monitorowania baterii.

CREATE TABLE IF NOT EXISTS `battery_units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_name` VARCHAR(255) NOT NULL,
  `goods_reception_document` VARCHAR(255) NOT NULL,
  `location` VARCHAR(255) NULL,
  `serial_number` VARCHAR(255) NULL UNIQUE,
  `last_charged` DATE NULL,
  `notes` TEXT NULL,
  `last_modified_by_user_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_goods_reception_document` (`goods_reception_document`),
  INDEX `idx_location` (`location`),
  FOREIGN KEY (`last_modified_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'pracownik') NOT NULL DEFAULT 'pracownik',
  `theme` VARCHAR(10) NOT NULL DEFAULT 'light',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- NOWA TABELA: Przechowuje ustawienia, takie jak data ostatniej synchronizacji.
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL
);

-- Wstawienie domyślnej wartości dla daty synchronizacji.
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('last_sync_time', 'Nigdy') ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;