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
CREATE TABLE IF NOT EXISTS `community_reactions` (
  `reaction_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT(20) UNSIGNED NOT NULL,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `reaction_type` ENUM('like','heart') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reaction_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: community_reactions table created / already exists</h2>";

  $row = $pdo->query("SHOW TABLES LIKE 'community_reactions'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: community_reactions exists.</p>" : "<p>Warning: community_reactions not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
