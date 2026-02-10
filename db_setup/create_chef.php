<?php
require_once __DIR__ . '/../config/conn.php';

$sql = "
CREATE TABLE IF NOT EXISTS chef (
  chef_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";

$pdo->exec($sql);
echo "OK: chef created";
