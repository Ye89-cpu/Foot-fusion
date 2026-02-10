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
CREATE TABLE IF NOT EXISTS `recipe_collection` (
  `recipe_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `dietary` VARCHAR(100) NOT NULL,
  `cuisine` VARCHAR(100) NOT NULL,
  `difficulty` VARCHAR(50) NOT NULL,
  `recipe_type` VARCHAR(100) NOT NULL,
  `ingredients` TEXT NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `chef_id` INT(10) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`recipe_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: recipe_collection table created / already exists</h2>";

  $row = $pdo->query("SHOW TABLES LIKE 'recipe_collection'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: recipe_collection exists.</p>" : "<p>Warning: recipe_collection not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
