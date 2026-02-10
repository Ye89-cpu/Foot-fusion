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
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `message_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(200) NOT NULL,

  
  `type` ENUM('Enquiry','Recipe Request','Feedback','Bug Report') NOT NULL DEFAULT 'Enquiry',

  `message` TEXT NOT NULL,
  `status` ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;
";

try {
  $pdo->exec($sql);
  echo "<h2>OK: contact_messages table created / already exists</h2>";

  $row = $pdo->query("SHOW TABLES LIKE 'contact_messages'")->fetch(PDO::FETCH_NUM);
  echo $row ? "<p>Verified: contact_messages exists.</p>" : "<p>Warning: contact_messages not found.</p>";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>FAILED</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
}
