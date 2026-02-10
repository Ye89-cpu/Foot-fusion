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
CREATE TABLE IF NOT EXISTS `educational_downloads` (
  `download_id` INT(11) NOT NULL AUTO_INCREMENT,
  `resource_id` INT(11) NOT NULL,
  `user_id` INT(11) NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`download_id`),
  KEY `idx_resource` (`resource_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: educational_downloads table created / already exists</h2>";

  $row = $pdo->query("SHOW TABLES LIKE 'educational_downloads'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: educational_downloads exists.</p>" : "<p>Warning: educational_downloads not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
