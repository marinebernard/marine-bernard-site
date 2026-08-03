<?php
session_start();
require_once '../api/config.php';

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

$sql = "CREATE TABLE IF NOT EXISTS parcours (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(255) NOT NULL,
  region VARCHAR(100) NOT NULL,
  distance DECIMAL(5,2) DEFAULT NULL COMMENT 'en km',
  duree VARCHAR(50) DEFAULT NULL COMMENT 'ex: 3h30',
  denivele INT DEFAULT NULL COMMENT 'en mètres',
  difficulte ENUM('facile','modere','difficile') DEFAULT 'facile',
  description TEXT DEFAULT NULL,
  coup_de_coeur TINYINT(1) DEFAULT 0,
  visible TINYINT(1) DEFAULT 1,
  photo_principale VARCHAR(500) DEFAULT NULL,
  photos_galerie TEXT DEFAULT NULL COMMENT 'JSON array de chemins',
  fichier_gpx VARCHAR(500) DEFAULT NULL,
  lien_komoot VARCHAR(500) DEFAULT NULL,
  tags VARCHAR(255) DEFAULT NULL COMMENT 'JSON array de tags',
  date_rando DATE DEFAULT NULL,
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
  $pdo->exec($sql);
  echo '<p style="font-family:sans-serif;color:green;padding:2rem">✓ Table parcours créée avec succès. <a href="/admin/login.php">Aller au back office →</a></p>';
} catch(PDOException $e) {
  echo '<p style="font-family:sans-serif;color:red;padding:2rem">Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
}
