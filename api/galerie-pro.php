<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$stmt = $pdo->query("SELECT * FROM galerie_pro WHERE visible = 1 ORDER BY ordre ASC, date_creation DESC");
$photos = $stmt->fetchAll();

echo json_encode([
  'total'  => count($photos),
  'photos' => $photos
], JSON_UNESCAPED_UNICODE);
