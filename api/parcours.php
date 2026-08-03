<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://marine-bernard.fr');

require_once 'config.php';

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur serveur.']);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$filtre = $_GET['filtre'] ?? 'all';
$coeur = isset($_GET['coup_de_coeur']) ? true : false;

if ($method === 'GET') {
  if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM parcours WHERE id = ? AND visible = 1");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if ($p) {
      $p['photos_galerie'] = json_decode($p['photos_galerie'] ?? '[]');
      $p['tags'] = json_decode($p['tags'] ?? '[]');
      echo json_encode($p, JSON_UNESCAPED_UNICODE);
    } else {
      http_response_code(404);
      echo json_encode(['error' => 'Non trouvé']);
    }
  } else {
    $sql = "SELECT * FROM parcours WHERE visible = 1";
    $params = [];
    if (in_array($filtre, ['facile','modere','difficile'])) {
      $sql .= " AND difficulte = ?";
      $params[] = $filtre;
    }
    if ($coeur) $sql .= " AND coup_de_coeur = 1";
    $sql .= " ORDER BY coup_de_coeur DESC, date_rando DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parcours = $stmt->fetchAll();
    foreach ($parcours as &$p) {
      $p['photos_galerie'] = json_decode($p['photos_galerie'] ?? '[]');
      $p['tags'] = json_decode($p['tags'] ?? '[]');
    }
    echo json_encode([
      'total' => count($parcours),
      'parcours' => $parcours
    ], JSON_UNESCAPED_UNICODE);
  }
} else {
  http_response_code(405);
  echo json_encode(['error' => 'Méthode non autorisée']);
}
