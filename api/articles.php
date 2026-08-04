<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur serveur.']);
  exit;
}

$categorie = $_GET['categorie'] ?? 'all';
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$slug = $_GET['slug'] ?? null;

if ($id || $slug) {
  if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND visible = 1");
    $stmt->execute([$id]);
  } else {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND visible = 1");
    $stmt->execute([$slug]);
  }
  $a = $stmt->fetch();
  if (!$a) http_response_code(404);
  echo json_encode($a ?: ['error' => 'Non trouvé'], JSON_UNESCAPED_UNICODE);
} else {
  $sql = "SELECT id, titre, slug, categorie, photo_principale, extrait, date_publication, temps_lecture FROM articles WHERE visible = 1";
  $params = [];
  if ($categorie !== 'all') { $sql .= " AND categorie = ?"; $params[] = $categorie; }
  $sql .= " ORDER BY date_publication DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $articles = $stmt->fetchAll();
  echo json_encode(['total' => count($articles), 'articles' => $articles], JSON_UNESCAPED_UNICODE);
}
