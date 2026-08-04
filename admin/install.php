<?php
session_start();
require_once __DIR__ . '/../api/config.php';

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  echo '<p style="font-family:sans-serif;color:red;padding:2rem">Erreur de connexion : ' . htmlspecialchars($e->getMessage()) . '</p>';
  exit;
}

$resultats = [];

$table = "CREATE TABLE IF NOT EXISTS parcours (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(255) NOT NULL,
  region VARCHAR(100) DEFAULT NULL,
  distance DECIMAL(5,2) DEFAULT NULL,
  duree VARCHAR(50) DEFAULT NULL,
  denivele INT DEFAULT NULL,
  difficulte ENUM('facile','modere','difficile') DEFAULT 'facile',
  type_paysage VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  visible TINYINT(1) DEFAULT 1,
  coup_de_coeur TINYINT(1) DEFAULT 0,
  photo_principale VARCHAR(500) DEFAULT NULL,
  photos_galerie TEXT DEFAULT NULL,
  fichier_gpx VARCHAR(500) DEFAULT NULL,
  lien_komoot VARCHAR(500) DEFAULT NULL,
  tags VARCHAR(255) DEFAULT NULL,
  date_rando DATE DEFAULT NULL,
  lieu_depart VARCHAR(255) DEFAULT NULL,
  lieu_lat DECIMAL(10,7) DEFAULT NULL,
  lieu_lng DECIMAL(10,7) DEFAULT NULL,
  lieu_ville VARCHAR(150) DEFAULT NULL,
  balisage TINYINT(1) DEFAULT 0,
  type_balisage VARCHAR(100) DEFAULT NULL,
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
  $pdo->exec($table);
  $resultats[] = ['ok', 'Table parcours créée ou déjà existante'];
} catch(PDOException $e) {
  $resultats[] = ['err', 'Création table : ' . $e->getMessage()];
}

$tableArticles = "CREATE TABLE IF NOT EXISTS articles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  categorie VARCHAR(100) DEFAULT 'randonnee',
  photo_principale VARCHAR(500) DEFAULT NULL,
  extrait TEXT DEFAULT NULL,
  contenu LONGTEXT DEFAULT NULL,
  visible TINYINT(1) DEFAULT 1,
  date_publication DATE DEFAULT NULL,
  temps_lecture INT DEFAULT NULL COMMENT 'en minutes',
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
  $pdo->exec($tableArticles);
  $resultats[] = ['ok', 'Table articles créée ou déjà existante'];
} catch(PDOException $e) {
  $resultats[] = ['err', 'Création table articles : ' . $e->getMessage()];
}

$colonnes = [
  'type_paysage'  => "ALTER TABLE parcours ADD COLUMN type_paysage VARCHAR(100) DEFAULT NULL",
  'lieu_depart'   => "ALTER TABLE parcours ADD COLUMN lieu_depart VARCHAR(255) DEFAULT NULL",
  'lieu_lat'      => "ALTER TABLE parcours ADD COLUMN lieu_lat DECIMAL(10,7) DEFAULT NULL",
  'lieu_lng'      => "ALTER TABLE parcours ADD COLUMN lieu_lng DECIMAL(10,7) DEFAULT NULL",
  'lieu_ville'    => "ALTER TABLE parcours ADD COLUMN lieu_ville VARCHAR(150) DEFAULT NULL",
  'balisage'      => "ALTER TABLE parcours ADD COLUMN balisage TINYINT(1) DEFAULT 0",
  'type_balisage' => "ALTER TABLE parcours ADD COLUMN type_balisage VARCHAR(100) DEFAULT NULL",
  'coup_de_coeur' => "ALTER TABLE parcours ADD COLUMN coup_de_coeur TINYINT(1) DEFAULT 0",
];

foreach ($colonnes as $col => $sql) {
  try {
    $pdo->exec($sql);
    $resultats[] = ['ok', "Colonne `$col` ajoutée"];
  } catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
      $resultats[] = ['exists', "Colonne `$col` déjà présente"];
    } else {
      $resultats[] = ['err', "Colonne `$col` : " . $e->getMessage()];
    }
  }
}

$stmt = $pdo->query("DESCRIBE parcours");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
$resultats[] = ['info', 'Colonnes actuelles : ' . implode(', ', $cols)];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Install — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;padding:2rem;color:#2D1B69}
    .card{background:#fff;border-radius:14px;padding:1.5rem;max-width:600px;border:.5px solid rgba(139,107,177,.18)}
    h1{font-size:16px;font-weight:500;margin-bottom:1rem;color:#2D1B69}
    .item{display:flex;align-items:flex-start;gap:8px;padding:.5rem .7rem;border-radius:8px;margin-bottom:6px;font-size:13px}
    .ok{background:#EAF3DE;color:#27500A}
    .exists{background:#F0E6FF;color:#2D1B69}
    .err{background:#FCEBEB;color:#A32D2D}
    .info{background:#FAF8FF;color:#5a4870;border:.5px solid rgba(139,107,177,.15)}
    .icon{flex-shrink:0;font-weight:600}
    a{display:inline-block;margin-top:1rem;background:#2D1B69;color:#fff;padding:8px 18px;border-radius:20px;font-size:13px;text-decoration:none}
  </style>
</head>
<body>
  <div class="card">
    <h1>🗄 Mise à jour base de données</h1>
    <?php foreach ($resultats as [$type, $msg]): ?>
      <div class="item <?= $type ?>">
        <span class="icon"><?= $type==='ok'?'✓':($type==='exists'?'→':($type==='err'?'✗':'ℹ')) ?></span>
        <span><?= htmlspecialchars($msg) ?></span>
      </div>
    <?php endforeach; ?>
    <a href="/admin/index.php">← Retour au back office</a>
  </div>
</body>
</html>
