<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/conn.php'; // $pdo

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  exit("DB connection error: \$pdo not found. Check config/conn.php");
}

$sql = "
CREATE TABLE IF NOT EXISTS `chef_profiles` (
  `chef_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `chef_name` VARCHAR(100) NOT NULL,
  `country` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chef_id`),
  UNIQUE KEY `uq_chef_user` (`user_id`),
  KEY `idx_chef_country` (`country`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: chef_profiles table created / already exists</h2>";

  $row = $pdo->query("SHOW TABLES LIKE 'chef_profiles'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: chef_profiles exists.</p>" : "<p>Warning: chef_profiles not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
