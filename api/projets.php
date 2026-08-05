<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$categorie = $_GET['categorie'] ?? 'all';
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM projets WHERE id = ? AND visible = 1");
  $stmt->execute([$id]);
  $p = $stmt->fetch();
  if ($p) {
    $p['photos_galerie'] = json_decode($p['photos_galerie'] ?? '[]');
    $p['outils'] = json_decode($p['outils'] ?? '[]');
  }
  echo json_encode($p ?: ['error' => 'Non trouvé'], JSON_UNESCAPED_UNICODE);
  exit;
}

$sql = "SELECT * FROM projets WHERE visible = 1";
$params = [];
if ($categorie !== 'all') { $sql .= " AND categorie = ?"; $params[] = $categorie; }
$sql .= " ORDER BY ordre ASC, date_creation DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projets = $stmt->fetchAll();

foreach ($projets as &$p) {
  $p['photos_galerie'] = json_decode($p['photos_galerie'] ?? '[]');
  $p['outils'] = json_decode($p['outils'] ?? '[]');
}
unset($p);

echo json_encode([
  'total'   => count($projets),
  'projets' => $projets
], JSON_UNESCAPED_UNICODE);
