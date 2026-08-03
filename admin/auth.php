<?php
// Réglés ici plutôt que via php_flag dans admin/.htaccess : la plupart des
// hébergeurs mutualisés (dont IONOS) exécutent PHP en FastCGI/PHP-FPM, où
// php_flag est un directive invalide — Apache renvoie alors une 500 pour
// tout le dossier admin/ avant même d'atteindre ce fichier.
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Le mot de passe en clair vit dans admin/config.php, jamais commité dans
// Git (voir .gitignore) — même logique que api/config.php pour les
// identifiants de base de données. Copie admin/config.sample.php pour
// créer ce fichier localement, puis upload-le à la main sur le serveur.
require_once __DIR__ . '/config.php';

define('ADMIN_PASSWORD_HASH', password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT));

function isLoggedIn() {
  return isset($_SESSION['admin_logged_in'])
    && $_SESSION['admin_logged_in'] === true
    && isset($_SESSION['admin_login_time'])
    && (time() - $_SESSION['admin_login_time']) < 3600;
}

function requireLogin() {
  if (!isLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
  }
}

function login($password) {
  if (password_verify($password, ADMIN_PASSWORD_HASH)) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_login_time'] = time();
    return true;
  }
  return false;
}

function logout() {
  $_SESSION = [];
  session_destroy();
  header('Location: /admin/login.php');
  exit;
}

if (isset($_GET['logout'])) {
  logout();
}
