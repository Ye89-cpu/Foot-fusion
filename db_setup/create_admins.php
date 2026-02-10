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
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: admins table created / already exists</h2>";

  // verify
  $row = $pdo->query("SHOW TABLES LIKE 'admins'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: admins exists.</p>" : "<p>Warning: admins not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
