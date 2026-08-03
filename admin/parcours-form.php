<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/upload.php';
requireLogin();

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$parcours = null;
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM parcours WHERE id = ?");
  $stmt->execute([$id]);
  $parcours = $stmt->fetch();
  if (!$parcours) { header('Location: /admin/index.php'); exit; }
  $parcours['photos_galerie'] = json_decode($parcours['photos_galerie'] ?? '[]', true);
  $parcours['tags'] = json_decode($parcours['tags'] ?? '[]', true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $photo_principale = $parcours['photo_principale'] ?? null;
  if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] === UPLOAD_ERR_OK) {
    $uploaded = uploadImage($_FILES['photo_principale']);
    if ($uploaded) $photo_principale = $uploaded;
  }

  $photos_galerie = $parcours['photos_galerie'] ?? [];
  if (isset($_FILES['photos_galerie']) && !empty($_FILES['photos_galerie']['name'][0])) {
    $new_photos = uploadMultipleImages($_FILES['photos_galerie']);
    $photos_galerie = array_merge($photos_galerie, $new_photos);
  }

  $fichier_gpx = $parcours['fichier_gpx'] ?? null;
  if (isset($_FILES['fichier_gpx']) && $_FILES['fichier_gpx']['error'] === UPLOAD_ERR_OK) {
    $uploaded_gpx = uploadGPX($_FILES['fichier_gpx']);
    if ($uploaded_gpx) $fichier_gpx = $uploaded_gpx;
  }

  $tags = array_map('trim', explode(',', $_POST['tags'] ?? ''));
  $tags = array_filter($tags);

  $data = [
    'titre' => trim($_POST['titre']),
    'region' => $_POST['region'],
    'distance' => !empty($_POST['distance']) ? (float)$_POST['distance'] : null,
    'duree' => trim($_POST['duree'] ?? ''),
    'denivele' => !empty($_POST['denivele']) ? (int)$_POST['denivele'] : null,
    'difficulte' => $_POST['difficulte'],
    'description' => trim($_POST['description'] ?? ''),
    'coup_de_coeur' => isset($_POST['coup_de_coeur']) ? 1 : 0,
    'visible' => isset($_POST['visible']) ? 1 : 0,
    'photo_principale' => $photo_principale,
    'photos_galerie' => json_encode(array_values($photos_galerie)),
    'fichier_gpx' => $fichier_gpx,
    'lien_komoot' => trim($_POST['lien_komoot'] ?? ''),
    'tags' => json_encode(array_values($tags)),
    'date_rando' => !empty($_POST['date_rando']) ? $_POST['date_rando'] : null,
  ];

  if ($id) {
    $sql = "UPDATE parcours SET titre=:titre, region=:region, distance=:distance, duree=:duree, denivele=:denivele, difficulte=:difficulte, description=:description, coup_de_coeur=:coup_de_coeur, visible=:visible, photo_principale=:photo_principale, photos_galerie=:photos_galerie, fichier_gpx=:fichier_gpx, lien_komoot=:lien_komoot, tags=:tags, date_rando=:date_rando WHERE id=:id";
    $data['id'] = $id;
  } else {
    $sql = "INSERT INTO parcours (titre, region, distance, duree, denivele, difficulte, description, coup_de_coeur, visible, photo_principale, photos_galerie, fichier_gpx, lien_komoot, tags, date_rando) VALUES (:titre, :region, :distance, :duree, :denivele, :difficulte, :description, :coup_de_coeur, :visible, :photo_principale, :photos_galerie, :fichier_gpx, :lien_komoot, :tags, :date_rando)";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute($data);
  header('Location: /admin/index.php?saved=1');
  exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? 'Modifier' : 'Ajouter' ?> un parcours — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;color:#2D1B69;min-height:100vh}
    .admin-header{background:#2D1B69;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
    .admin-logo{font-family:'Playfair Display',serif;font-style:italic;font-size:15px;color:#fff}
    .admin-nav a{font-size:12px;color:rgba(255,255,255,.7);text-decoration:none}
    .content{padding:2rem;max-width:800px;margin:0 auto}
    .page-title{font-family:'Playfair Display',serif;font-size:22px;color:#2D1B69;margin-bottom:1.5rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .form-group{display:flex;flex-direction:column;gap:.4rem;margin-bottom:.8rem}
    .form-group.full{grid-column:1/3}
    label{font-size:12px;font-weight:500;color:#5a4870;letter-spacing:.04em}
    input,select,textarea{border:.5px solid rgba(139,107,177,.3);border-radius:10px;padding:9px 12px;font-size:13px;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;width:100%;background:#fff}
    input:focus,select:focus,textarea:focus{border-color:#8B6BB1}
    textarea{resize:vertical;min-height:100px}
    .checkbox-group{display:flex;align-items:center;gap:8px;font-size:13px;color:#2D1B69}
    .checkbox-group input{width:auto}
    .upload-preview{margin-top:.5rem}
    .upload-preview img{width:80px;height:60px;object-fit:cover;border-radius:8px;border:.5px solid rgba(139,107,177,.2)}
    .upload-preview .gpx-file{font-size:11px;color:#3B6D11;background:#EAF3DE;padding:3px 8px;border-radius:6px;display:inline-block}
    .form-section{background:#fff;border:.5px solid rgba(139,107,177,.15);border-radius:14px;padding:1.2rem;margin-bottom:1rem}
    .form-section-title{font-size:12px;font-weight:600;color:#8B6BB1;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:.5px solid #F0E6FF}
    .btn-submit{background:#2D1B69;color:#fff;border:none;border-radius:22px;padding:12px 28px;font-size:14px;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s}
    .btn-submit:hover{background:#8B6BB1}
    .btn-cancel{background:#F0E6FF;color:#2D1B69;border:none;border-radius:22px;padding:12px 20px;font-size:14px;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;display:inline-block}
    .form-actions{display:flex;gap:10px;margin-top:1.5rem}
  </style>
</head>
<body>
  <header class="admin-header">
    <span class="admin-logo">Marine Bernard ✿</span>
    <nav class="admin-nav">
      <a href="/admin/index.php">← Retour à la liste</a>
    </nav>
  </header>
  <div class="content">
    <h1 class="page-title"><?= $id ? 'Modifier le parcours' : 'Ajouter un parcours' ?></h1>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-section">
        <p class="form-section-title">Informations générales</p>
        <div class="form-grid">
          <div class="form-group full">
            <label>Titre du parcours *</label>
            <input type="text" name="titre" value="<?= htmlspecialchars($parcours['titre'] ?? '') ?>" required placeholder="Ex: Cap Blanc-Nez — Sentier du littoral">
          </div>
          <div class="form-group">
            <label>Région</label>
            <select name="region">
              <?php foreach (['Côte d\'Opale','Artois','Flandre','Picardie','Autre'] as $r): ?>
              <option value="<?= $r ?>" <?= ($parcours['region'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Date de la randonnée</label>
            <input type="date" name="date_rando" value="<?= $parcours['date_rando'] ?? '' ?>">
          </div>
          <div class="form-group">
            <label>Distance (km)</label>
            <input type="number" name="distance" step="0.1" min="0" value="<?= $parcours['distance'] ?? '' ?>" placeholder="Ex: 18.5">
          </div>
          <div class="form-group">
            <label>Durée</label>
            <input type="text" name="duree" value="<?= htmlspecialchars($parcours['duree'] ?? '') ?>" placeholder="Ex: 3h30">
          </div>
          <div class="form-group">
            <label>Dénivelé (mètres)</label>
            <input type="number" name="denivele" min="0" value="<?= $parcours['denivele'] ?? '' ?>" placeholder="Ex: 250">
          </div>
          <div class="form-group">
            <label>Difficulté</label>
            <select name="difficulte">
              <option value="facile" <?= ($parcours['difficulte'] ?? '') === 'facile' ? 'selected' : '' ?>>Facile</option>
              <option value="modere" <?= ($parcours['difficulte'] ?? '') === 'modere' ? 'selected' : '' ?>>Modéré</option>
              <option value="difficile" <?= ($parcours['difficulte'] ?? '') === 'difficile' ? 'selected' : '' ?>>Difficile</option>
            </select>
          </div>
          <div class="form-group">
            <label>Tags (séparés par des virgules)</label>
            <input type="text" name="tags" value="<?= htmlspecialchars(implode(', ', $parcours['tags'] ?? [])) ?>" placeholder="vue mer, faune, printemps">
          </div>
          <div class="form-group full">
            <label>Description</label>
            <textarea name="description" placeholder="Décris ce sentier — ambiance, points d'intérêt, conseils..."><?= htmlspecialchars($parcours['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Lien Komoot (optionnel)</label>
            <input type="url" name="lien_komoot" value="<?= htmlspecialchars($parcours['lien_komoot'] ?? '') ?>" placeholder="https://www.komoot.com/tour/...">
          </div>
          <div class="form-group" style="justify-content:flex-end">
            <div class="checkbox-group">
              <input type="checkbox" name="coup_de_coeur" id="coeur" <?= ($parcours['coup_de_coeur'] ?? 0) ? 'checked' : '' ?>>
              <label for="coeur">✦ Coup de cœur</label>
            </div>
            <div class="checkbox-group" style="margin-top:.5rem">
              <input type="checkbox" name="visible" id="visible" <?= ($parcours['visible'] ?? 1) ? 'checked' : '' ?>>
              <label for="visible">Visible sur le site</label>
            </div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <p class="form-section-title">Photos</p>
        <div class="form-group">
          <label>Photo principale</label>
          <input type="file" name="photo_principale" accept="image/*">
          <?php if (!empty($parcours['photo_principale'])): ?>
          <div class="upload-preview"><img src="/<?= htmlspecialchars($parcours['photo_principale']) ?>" alt="Photo actuelle"> <span style="font-size:11px;color:#9a88b8">Photo actuelle</span></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>Photos galerie (plusieurs possibles)</label>
          <input type="file" name="photos_galerie[]" accept="image/*" multiple>
          <?php if (!empty($parcours['photos_galerie'])): ?>
          <div class="upload-preview" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:.5rem">
            <?php foreach ($parcours['photos_galerie'] as $photo): ?>
            <img src="/<?= htmlspecialchars($photo) ?>" alt="">
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-section">
        <p class="form-section-title">Trace GPS</p>
        <div class="form-group">
          <label>Fichier GPX</label>
          <input type="file" name="fichier_gpx" accept=".gpx">
          <?php if (!empty($parcours['fichier_gpx'])): ?>
          <div class="upload-preview"><span class="gpx-file">📍 <?= htmlspecialchars(basename($parcours['fichier_gpx'])) ?></span> <span style="font-size:11px;color:#9a88b8">GPX actuel</span></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-submit">Enregistrer le parcours</button>
        <a href="/admin/index.php" class="btn-cancel">Annuler</a>
      </div>
    </form>
  </div>
</body>
</html>
