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
CREATE TABLE IF NOT EXISTS `community_posts` (
  `post_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,

  `post_type` ENUM('user_post','chef_recipe') NOT NULL DEFAULT 'user_post',
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,

  `cover_image` VARCHAR(255) NULL DEFAULT NULL,
  `cuisine` VARCHAR(100) NULL DEFAULT NULL,
  `difficulty` ENUM('Easy','Medium','Hard') NULL DEFAULT NULL,
  `prep_time` INT(11) NULL DEFAULT NULL,
  `cook_time` INT(11) NULL DEFAULT NULL,
  `ingredients` TEXT NULL DEFAULT NULL,
  `instructions` TEXT NULL DEFAULT NULL,

  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` INT(11) NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `reject_reason` VARCHAR(255) NULL DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`post_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: community_posts table created / already exists</h2>";

  $row = $pdo->query("SHOW TABLES LIKE 'community_posts'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: community_posts exists.</p>" : "<p>Warning: community_posts not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
