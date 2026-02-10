<?php
// $server_name = "sql107.infinityfree.com";
// $user_name   = "if0_41077372";
// $password    = "qQqQhMETIr
// ";
// //  $dbname      = "food-fusion-dbd";
// $dbname      = " if0_41077372_Food_Fushion_DB";

$host = "sql107.infinityfree.com";
$user = "if0_4107732";
$pass = "YOUR_VPANEL_PASSWORD"; // same password you use to login vPanel
$db   = "if0_4107732_Food_Fushion_DB";

try {
$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user_name,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

} catch (PDOException $e) {
    http_response_code(500);
    die("Database connection failed.");
} 