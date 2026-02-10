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
CREATE TABLE IF NOT EXISTS `culinary_resources` (
  `resource_id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `topic` VARCHAR(120) NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'Cooking Basics',
  `resource_type` ENUM('Recipe Card','Tutorial','Video','Infographic') NOT NULL DEFAULT 'Recipe Card',
  `description` TEXT NULL DEFAULT NULL,
  `thumbnail_url` VARCHAR(255) NULL DEFAULT NULL,
  `file_url` VARCHAR(255) NULL DEFAULT NULL,
  `video_url` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`resource_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: culinary_resources table created / already exists</h2>";

  $row = $pdo->query("SHOW TABLES LIKE 'culinary_resources'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: culinary_resources exists.</p>" : "<p>Warning: culinary_resources not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
