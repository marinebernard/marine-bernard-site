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

  if (!empty($_POST['supprimer_photo_principale'])) {
    if ($photo_principale) {
      $full = __DIR__ . '/../' . $photo_principale;
      if (file_exists($full)) @unlink($full);
    }
    $photo_principale = null;
  }

  if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] === UPLOAD_ERR_OK) {
    $new = uploadImage($_FILES['photo_principale']);
    if ($new) $photo_principale = $new;
  }

  $photos_conserver = $_POST['photos_conserver'] ?? [];
  $photos_supprimer = $_POST['photos_supprimer'] ?? [];
  $photos_galerie   = [];

  foreach ($photos_conserver as $i => $photo) {
    if (!empty($photo)) {
      $a_supprimer = isset($photos_supprimer[$i]) && $photos_supprimer[$i] === '1';
      if ($a_supprimer) {
        $full = __DIR__ . '/../' . $photo;
        if (file_exists($full)) @unlink($full);
      } else {
        $photos_galerie[] = $photo;
      }
    }
  }

  if (isset($_FILES['photos_galerie']) && !empty($_FILES['photos_galerie']['name'][0])) {
    $nouvelles = uploadMultipleImages($_FILES['photos_galerie']);
    $photos_galerie = array_merge($photos_galerie, $nouvelles);
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
    'type_paysage' => $_POST['type_paysage'] ?? null,
    'description' => trim($_POST['description'] ?? ''),
    'balisage' => isset($_POST['balisage']) ? 1 : 0,
    'type_balisage' => isset($_POST['balisage']) ? ($_POST['type_balisage'] ?? null) : null,
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
    $sql = "UPDATE parcours SET titre=:titre, region=:region, distance=:distance, duree=:duree, denivele=:denivele, difficulte=:difficulte, type_paysage=:type_paysage, description=:description, balisage=:balisage, type_balisage=:type_balisage, coup_de_coeur=:coup_de_coeur, visible=:visible, photo_principale=:photo_principale, photos_galerie=:photos_galerie, fichier_gpx=:fichier_gpx, lien_komoot=:lien_komoot, tags=:tags, date_rando=:date_rando WHERE id=:id";
    $data['id'] = $id;
  } else {
    $sql = "INSERT INTO parcours (titre, region, distance, duree, denivele, difficulte, type_paysage, description, balisage, type_balisage, coup_de_coeur, visible, photo_principale, photos_galerie, fichier_gpx, lien_komoot, tags, date_rando) VALUES (:titre, :region, :distance, :duree, :denivele, :difficulte, :type_paysage, :description, :balisage, :type_balisage, :coup_de_coeur, :visible, :photo_principale, :photos_galerie, :fichier_gpx, :lien_komoot, :tags, :date_rando)";
  }

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    header('Location: /admin/index.php?saved=1');
    exit;
  } catch (PDOException $e) {
    $form_error = 'Erreur base de données : ' . $e->getMessage();
  }
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
    .balisage-option{display:flex;align-items:flex-start;gap:10px;padding:.6rem .8rem;border-radius:10px;border:.5px solid rgba(139,107,177,.15);cursor:pointer;transition:background .15s,border-color .15s}
    .balisage-option:hover{background:#F0E6FF}
    .balisage-option.selected-balisage{background:#F0E6FF;border-color:#8B6BB1}
    .balisage-option input[type="radio"]{margin-top:3px;accent-color:#2D1B69;width:auto}
    .balisage-option-label{font-size:13px;font-weight:500;color:#2D1B69}
    .balisage-option-desc{font-size:11px;color:#9a88b8;margin-top:1px}
    .form-section{background:#fff;border:.5px solid rgba(139,107,177,.15);border-radius:14px;padding:1.2rem;margin-bottom:1rem}
    .form-section-title{font-size:12px;font-weight:600;color:#8B6BB1;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:.5px solid #F0E6FF}
    .btn-submit{background:#2D1B69;color:#fff;border:none;border-radius:22px;padding:12px 28px;font-size:14px;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s}
    .btn-submit:hover{background:#8B6BB1}
    .btn-cancel{background:#F0E6FF;color:#2D1B69;border:none;border-radius:22px;padding:12px 20px;font-size:14px;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;display:inline-block}
    .form-actions{display:flex;gap:10px;margin-top:1.5rem}
    .btn-card-form{background:#F0E6FF;color:#2D1B69;border:none;border-radius:22px;padding:9px 20px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s}
    .btn-card-form:hover{background:#d4b8f0}
    .btn-card-form.loading{opacity:.6;cursor:wait}
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
    <?php if (!empty($form_error)): ?>
    <div style="background:#FCEBEB;color:#A32D2D;border-radius:8px;padding:.7rem 1rem;font-size:13px;margin-bottom:1rem">
      <?= htmlspecialchars($form_error) ?>
    </div>
    <?php endif; ?>
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
              <optgroup label="Hauts-de-France">
                <option value="Nord" <?= ($parcours['region'] ?? '') === 'Nord' ? 'selected' : '' ?>>Nord</option>
                <option value="Pas-de-Calais" <?= ($parcours['region'] ?? '') === 'Pas-de-Calais' ? 'selected' : '' ?>>Pas-de-Calais</option>
                <option value="Somme" <?= ($parcours['region'] ?? '') === 'Somme' ? 'selected' : '' ?>>Somme</option>
                <option value="Aisne" <?= ($parcours['region'] ?? '') === 'Aisne' ? 'selected' : '' ?>>Aisne</option>
                <option value="Oise" <?= ($parcours['region'] ?? '') === 'Oise' ? 'selected' : '' ?>>Oise</option>
              </optgroup>
              <optgroup label="Normandie">
                <option value="Calvados" <?= ($parcours['region'] ?? '') === 'Calvados' ? 'selected' : '' ?>>Calvados</option>
                <option value="Eure" <?= ($parcours['region'] ?? '') === 'Eure' ? 'selected' : '' ?>>Eure</option>
                <option value="Manche" <?= ($parcours['region'] ?? '') === 'Manche' ? 'selected' : '' ?>>Manche</option>
                <option value="Orne" <?= ($parcours['region'] ?? '') === 'Orne' ? 'selected' : '' ?>>Orne</option>
                <option value="Seine-Maritime" <?= ($parcours['region'] ?? '') === 'Seine-Maritime' ? 'selected' : '' ?>>Seine-Maritime</option>
              </optgroup>
              <optgroup label="Bretagne">
                <option value="Côtes-d'Armor" <?= ($parcours['region'] ?? '') === "Côtes-d'Armor" ? 'selected' : '' ?>>Côtes-d'Armor</option>
                <option value="Finistère" <?= ($parcours['region'] ?? '') === 'Finistère' ? 'selected' : '' ?>>Finistère</option>
                <option value="Ille-et-Vilaine" <?= ($parcours['region'] ?? '') === 'Ille-et-Vilaine' ? 'selected' : '' ?>>Ille-et-Vilaine</option>
                <option value="Morbihan" <?= ($parcours['region'] ?? '') === 'Morbihan' ? 'selected' : '' ?>>Morbihan</option>
              </optgroup>
              <optgroup label="Pays de la Loire">
                <option value="Loire-Atlantique" <?= ($parcours['region'] ?? '') === 'Loire-Atlantique' ? 'selected' : '' ?>>Loire-Atlantique</option>
                <option value="Maine-et-Loire" <?= ($parcours['region'] ?? '') === 'Maine-et-Loire' ? 'selected' : '' ?>>Maine-et-Loire</option>
                <option value="Mayenne" <?= ($parcours['region'] ?? '') === 'Mayenne' ? 'selected' : '' ?>>Mayenne</option>
                <option value="Sarthe" <?= ($parcours['region'] ?? '') === 'Sarthe' ? 'selected' : '' ?>>Sarthe</option>
                <option value="Vendée" <?= ($parcours['region'] ?? '') === 'Vendée' ? 'selected' : '' ?>>Vendée</option>
              </optgroup>
              <optgroup label="Centre-Val de Loire">
                <option value="Cher" <?= ($parcours['region'] ?? '') === 'Cher' ? 'selected' : '' ?>>Cher</option>
                <option value="Eure-et-Loir" <?= ($parcours['region'] ?? '') === 'Eure-et-Loir' ? 'selected' : '' ?>>Eure-et-Loir</option>
                <option value="Indre" <?= ($parcours['region'] ?? '') === 'Indre' ? 'selected' : '' ?>>Indre</option>
                <option value="Indre-et-Loire" <?= ($parcours['region'] ?? '') === 'Indre-et-Loire' ? 'selected' : '' ?>>Indre-et-Loire</option>
                <option value="Loir-et-Cher" <?= ($parcours['region'] ?? '') === 'Loir-et-Cher' ? 'selected' : '' ?>>Loir-et-Cher</option>
                <option value="Loiret" <?= ($parcours['region'] ?? '') === 'Loiret' ? 'selected' : '' ?>>Loiret</option>
              </optgroup>
              <optgroup label="Île-de-France">
                <option value="Paris" <?= ($parcours['region'] ?? '') === 'Paris' ? 'selected' : '' ?>>Paris</option>
                <option value="Seine-et-Marne" <?= ($parcours['region'] ?? '') === 'Seine-et-Marne' ? 'selected' : '' ?>>Seine-et-Marne</option>
                <option value="Yvelines" <?= ($parcours['region'] ?? '') === 'Yvelines' ? 'selected' : '' ?>>Yvelines</option>
                <option value="Essonne" <?= ($parcours['region'] ?? '') === 'Essonne' ? 'selected' : '' ?>>Essonne</option>
                <option value="Hauts-de-Seine" <?= ($parcours['region'] ?? '') === 'Hauts-de-Seine' ? 'selected' : '' ?>>Hauts-de-Seine</option>
                <option value="Seine-Saint-Denis" <?= ($parcours['region'] ?? '') === 'Seine-Saint-Denis' ? 'selected' : '' ?>>Seine-Saint-Denis</option>
                <option value="Val-de-Marne" <?= ($parcours['region'] ?? '') === 'Val-de-Marne' ? 'selected' : '' ?>>Val-de-Marne</option>
                <option value="Val-d'Oise" <?= ($parcours['region'] ?? '') === "Val-d'Oise" ? 'selected' : '' ?>>Val-d'Oise</option>
              </optgroup>
              <optgroup label="Grand Est">
                <option value="Ardennes" <?= ($parcours['region'] ?? '') === 'Ardennes' ? 'selected' : '' ?>>Ardennes</option>
                <option value="Aube" <?= ($parcours['region'] ?? '') === 'Aube' ? 'selected' : '' ?>>Aube</option>
                <option value="Marne" <?= ($parcours['region'] ?? '') === 'Marne' ? 'selected' : '' ?>>Marne</option>
                <option value="Haute-Marne" <?= ($parcours['region'] ?? '') === 'Haute-Marne' ? 'selected' : '' ?>>Haute-Marne</option>
                <option value="Meurthe-et-Moselle" <?= ($parcours['region'] ?? '') === 'Meurthe-et-Moselle' ? 'selected' : '' ?>>Meurthe-et-Moselle</option>
                <option value="Meuse" <?= ($parcours['region'] ?? '') === 'Meuse' ? 'selected' : '' ?>>Meuse</option>
                <option value="Moselle" <?= ($parcours['region'] ?? '') === 'Moselle' ? 'selected' : '' ?>>Moselle</option>
                <option value="Bas-Rhin" <?= ($parcours['region'] ?? '') === 'Bas-Rhin' ? 'selected' : '' ?>>Bas-Rhin</option>
                <option value="Haut-Rhin" <?= ($parcours['region'] ?? '') === 'Haut-Rhin' ? 'selected' : '' ?>>Haut-Rhin</option>
                <option value="Vosges" <?= ($parcours['region'] ?? '') === 'Vosges' ? 'selected' : '' ?>>Vosges</option>
              </optgroup>
              <optgroup label="Bourgogne-Franche-Comté">
                <option value="Côte-d'Or" <?= ($parcours['region'] ?? '') === "Côte-d'Or" ? 'selected' : '' ?>>Côte-d'Or</option>
                <option value="Doubs" <?= ($parcours['region'] ?? '') === 'Doubs' ? 'selected' : '' ?>>Doubs</option>
                <option value="Jura" <?= ($parcours['region'] ?? '') === 'Jura' ? 'selected' : '' ?>>Jura</option>
                <option value="Nièvre" <?= ($parcours['region'] ?? '') === 'Nièvre' ? 'selected' : '' ?>>Nièvre</option>
                <option value="Haute-Saône" <?= ($parcours['region'] ?? '') === 'Haute-Saône' ? 'selected' : '' ?>>Haute-Saône</option>
                <option value="Saône-et-Loire" <?= ($parcours['region'] ?? '') === 'Saône-et-Loire' ? 'selected' : '' ?>>Saône-et-Loire</option>
                <option value="Yonne" <?= ($parcours['region'] ?? '') === 'Yonne' ? 'selected' : '' ?>>Yonne</option>
                <option value="Territoire de Belfort" <?= ($parcours['region'] ?? '') === 'Territoire de Belfort' ? 'selected' : '' ?>>Territoire de Belfort</option>
              </optgroup>
              <optgroup label="Auvergne-Rhône-Alpes">
                <option value="Ain" <?= ($parcours['region'] ?? '') === 'Ain' ? 'selected' : '' ?>>Ain</option>
                <option value="Allier" <?= ($parcours['region'] ?? '') === 'Allier' ? 'selected' : '' ?>>Allier</option>
                <option value="Ardèche" <?= ($parcours['region'] ?? '') === 'Ardèche' ? 'selected' : '' ?>>Ardèche</option>
                <option value="Cantal" <?= ($parcours['region'] ?? '') === 'Cantal' ? 'selected' : '' ?>>Cantal</option>
                <option value="Drôme" <?= ($parcours['region'] ?? '') === 'Drôme' ? 'selected' : '' ?>>Drôme</option>
                <option value="Isère" <?= ($parcours['region'] ?? '') === 'Isère' ? 'selected' : '' ?>>Isère</option>
                <option value="Loire" <?= ($parcours['region'] ?? '') === 'Loire' ? 'selected' : '' ?>>Loire</option>
                <option value="Haute-Loire" <?= ($parcours['region'] ?? '') === 'Haute-Loire' ? 'selected' : '' ?>>Haute-Loire</option>
                <option value="Puy-de-Dôme" <?= ($parcours['region'] ?? '') === 'Puy-de-Dôme' ? 'selected' : '' ?>>Puy-de-Dôme</option>
                <option value="Rhône" <?= ($parcours['region'] ?? '') === 'Rhône' ? 'selected' : '' ?>>Rhône</option>
                <option value="Savoie" <?= ($parcours['region'] ?? '') === 'Savoie' ? 'selected' : '' ?>>Savoie</option>
                <option value="Haute-Savoie" <?= ($parcours['region'] ?? '') === 'Haute-Savoie' ? 'selected' : '' ?>>Haute-Savoie</option>
              </optgroup>
              <optgroup label="Nouvelle-Aquitaine">
                <option value="Charente" <?= ($parcours['region'] ?? '') === 'Charente' ? 'selected' : '' ?>>Charente</option>
                <option value="Charente-Maritime" <?= ($parcours['region'] ?? '') === 'Charente-Maritime' ? 'selected' : '' ?>>Charente-Maritime</option>
                <option value="Corrèze" <?= ($parcours['region'] ?? '') === 'Corrèze' ? 'selected' : '' ?>>Corrèze</option>
                <option value="Creuse" <?= ($parcours['region'] ?? '') === 'Creuse' ? 'selected' : '' ?>>Creuse</option>
                <option value="Dordogne" <?= ($parcours['region'] ?? '') === 'Dordogne' ? 'selected' : '' ?>>Dordogne</option>
                <option value="Gironde" <?= ($parcours['region'] ?? '') === 'Gironde' ? 'selected' : '' ?>>Gironde</option>
                <option value="Landes" <?= ($parcours['region'] ?? '') === 'Landes' ? 'selected' : '' ?>>Landes</option>
                <option value="Lot-et-Garonne" <?= ($parcours['region'] ?? '') === 'Lot-et-Garonne' ? 'selected' : '' ?>>Lot-et-Garonne</option>
                <option value="Pyrénées-Atlantiques" <?= ($parcours['region'] ?? '') === 'Pyrénées-Atlantiques' ? 'selected' : '' ?>>Pyrénées-Atlantiques</option>
                <option value="Deux-Sèvres" <?= ($parcours['region'] ?? '') === 'Deux-Sèvres' ? 'selected' : '' ?>>Deux-Sèvres</option>
                <option value="Vienne" <?= ($parcours['region'] ?? '') === 'Vienne' ? 'selected' : '' ?>>Vienne</option>
                <option value="Haute-Vienne" <?= ($parcours['region'] ?? '') === 'Haute-Vienne' ? 'selected' : '' ?>>Haute-Vienne</option>
              </optgroup>
              <optgroup label="Occitanie">
                <option value="Ariège" <?= ($parcours['region'] ?? '') === 'Ariège' ? 'selected' : '' ?>>Ariège</option>
                <option value="Aude" <?= ($parcours['region'] ?? '') === 'Aude' ? 'selected' : '' ?>>Aude</option>
                <option value="Aveyron" <?= ($parcours['region'] ?? '') === 'Aveyron' ? 'selected' : '' ?>>Aveyron</option>
                <option value="Gard" <?= ($parcours['region'] ?? '') === 'Gard' ? 'selected' : '' ?>>Gard</option>
                <option value="Haute-Garonne" <?= ($parcours['region'] ?? '') === 'Haute-Garonne' ? 'selected' : '' ?>>Haute-Garonne</option>
                <option value="Gers" <?= ($parcours['region'] ?? '') === 'Gers' ? 'selected' : '' ?>>Gers</option>
                <option value="Hérault" <?= ($parcours['region'] ?? '') === 'Hérault' ? 'selected' : '' ?>>Hérault</option>
                <option value="Lot" <?= ($parcours['region'] ?? '') === 'Lot' ? 'selected' : '' ?>>Lot</option>
                <option value="Lozère" <?= ($parcours['region'] ?? '') === 'Lozère' ? 'selected' : '' ?>>Lozère</option>
                <option value="Hautes-Pyrénées" <?= ($parcours['region'] ?? '') === 'Hautes-Pyrénées' ? 'selected' : '' ?>>Hautes-Pyrénées</option>
                <option value="Pyrénées-Orientales" <?= ($parcours['region'] ?? '') === 'Pyrénées-Orientales' ? 'selected' : '' ?>>Pyrénées-Orientales</option>
                <option value="Tarn" <?= ($parcours['region'] ?? '') === 'Tarn' ? 'selected' : '' ?>>Tarn</option>
                <option value="Tarn-et-Garonne" <?= ($parcours['region'] ?? '') === 'Tarn-et-Garonne' ? 'selected' : '' ?>>Tarn-et-Garonne</option>
              </optgroup>
              <optgroup label="Provence-Alpes-Côte d'Azur">
                <option value="Alpes-de-Haute-Provence" <?= ($parcours['region'] ?? '') === 'Alpes-de-Haute-Provence' ? 'selected' : '' ?>>Alpes-de-Haute-Provence</option>
                <option value="Hautes-Alpes" <?= ($parcours['region'] ?? '') === 'Hautes-Alpes' ? 'selected' : '' ?>>Hautes-Alpes</option>
                <option value="Alpes-Maritimes" <?= ($parcours['region'] ?? '') === 'Alpes-Maritimes' ? 'selected' : '' ?>>Alpes-Maritimes</option>
                <option value="Bouches-du-Rhône" <?= ($parcours['region'] ?? '') === 'Bouches-du-Rhône' ? 'selected' : '' ?>>Bouches-du-Rhône</option>
                <option value="Var" <?= ($parcours['region'] ?? '') === 'Var' ? 'selected' : '' ?>>Var</option>
                <option value="Vaucluse" <?= ($parcours['region'] ?? '') === 'Vaucluse' ? 'selected' : '' ?>>Vaucluse</option>
              </optgroup>
              <optgroup label="Corse">
                <option value="Corse-du-Sud" <?= ($parcours['region'] ?? '') === 'Corse-du-Sud' ? 'selected' : '' ?>>Corse-du-Sud</option>
                <option value="Haute-Corse" <?= ($parcours['region'] ?? '') === 'Haute-Corse' ? 'selected' : '' ?>>Haute-Corse</option>
              </optgroup>
              <optgroup label="Outre-mer">
                <option value="Guadeloupe" <?= ($parcours['region'] ?? '') === 'Guadeloupe' ? 'selected' : '' ?>>Guadeloupe</option>
                <option value="Martinique" <?= ($parcours['region'] ?? '') === 'Martinique' ? 'selected' : '' ?>>Martinique</option>
                <option value="Guyane" <?= ($parcours['region'] ?? '') === 'Guyane' ? 'selected' : '' ?>>Guyane</option>
                <option value="La Réunion" <?= ($parcours['region'] ?? '') === 'La Réunion' ? 'selected' : '' ?>>La Réunion</option>
                <option value="Mayotte" <?= ($parcours['region'] ?? '') === 'Mayotte' ? 'selected' : '' ?>>Mayotte</option>
              </optgroup>
              <optgroup label="Autre">
                <option value="Autre" <?= ($parcours['region'] ?? '') === 'Autre' ? 'selected' : '' ?>>Autre</option>
              </optgroup>
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
            <label>Type de paysage</label>
            <select name="type_paysage">
              <option value="">— Non précisé —</option>
              <option value="littoral" <?= ($parcours['type_paysage'] ?? '') === 'littoral' ? 'selected' : '' ?>>🌊 Littoral</option>
              <option value="foret" <?= ($parcours['type_paysage'] ?? '') === 'foret' ? 'selected' : '' ?>>🌲 Forêt</option>
              <option value="campagne" <?= ($parcours['type_paysage'] ?? '') === 'campagne' ? 'selected' : '' ?>>🌾 Campagne</option>
              <option value="montagne" <?= ($parcours['type_paysage'] ?? '') === 'montagne' ? 'selected' : '' ?>>⛰️ Montagne</option>
              <option value="patrimoine" <?= ($parcours['type_paysage'] ?? '') === 'patrimoine' ? 'selected' : '' ?>>🏛️ Patrimoine</option>
              <option value="marais" <?= ($parcours['type_paysage'] ?? '') === 'marais' ? 'selected' : '' ?>>🦢 Zone humide</option>
              <option value="parc" <?= ($parcours['type_paysage'] ?? '') === 'parc' ? 'selected' : '' ?>>🌳 Parc</option>
              <option value="ville" <?= ($parcours['type_paysage'] ?? '') === 'ville' ? 'selected' : '' ?>>🏙️ Ville</option>
              <option value="terril" <?= ($parcours['type_paysage'] ?? '') === 'terril' ? 'selected' : '' ?>>⛏️ Terril</option>
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
        <p class="form-section-title">🔶 Balisage du sentier</p>
        <div class="form-grid">

          <div class="form-group full">
            <div class="checkbox-group">
              <input type="checkbox"
                name="balisage"
                id="balisage"
                value="1"
                <?= ($parcours['balisage'] ?? 0) ? 'checked' : '' ?>
                onchange="document.getElementById('typeBalisageWrap').style.display=this.checked?'block':'none'">
              <label for="balisage" style="font-size:13px;color:#2D1B69;font-weight:500">Ce sentier est balisé</label>
            </div>
          </div>

          <div class="form-group full" id="typeBalisageWrap"
               style="display:<?= ($parcours['balisage'] ?? 0) ? 'block' : 'none' ?>">
            <label>Type de balisage</label>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:.5rem">

              <?php
              $balises = [
                'jaune'       => ['emoji' => '🟡', 'label' => 'Trait jaune', 'desc' => 'Sentier de promenade et randonnée (PR) — moins de 2 jours'],
                'rouge'       => ['emoji' => '🔴', 'label' => 'Trait rouge', 'desc' => 'Grande randonnée de pays (GRP) — boucles régionales'],
                'rouge_blanc' => ['emoji' => '🔴⬜', 'label' => 'Trait rouge et blanc', 'desc' => 'Grande randonnée (GR) — longue distance nationale'],
                'orange'      => ['emoji' => '🟠', 'label' => 'Trait orange', 'desc' => 'Sentier de pays — itinéraires locaux touristiques'],
                'bleu'        => ['emoji' => '🔵', 'label' => 'Trait bleu', 'desc' => 'Sentier en forêt domaniale ONF'],
                'vert'        => ['emoji' => '🟢', 'label' => 'Trait vert', 'desc' => 'Véloroute ou voie verte'],
                'mixte'       => ['emoji' => '🎨', 'label' => 'Balisage mixte', 'desc' => 'Plusieurs types de balisage sur le même sentier'],
                'autre'       => ['emoji' => '❓', 'label' => 'Autre', 'desc' => 'Signalétique locale ou panneau directionnel'],
              ];
              $currentBalisage = $parcours['type_balisage'] ?? '';
              foreach ($balises as $val => $b):
              ?>
              <label class="balisage-option <?= $currentBalisage === $val ? 'selected-balisage' : '' ?>">
                <input type="radio" name="type_balisage" value="<?= $val ?>"
                       <?= $currentBalisage === $val ? 'checked' : '' ?>
                       onchange="document.querySelectorAll('.balisage-option').forEach(l=>l.classList.remove('selected-balisage'));this.closest('.balisage-option').classList.add('selected-balisage')">
                <div>
                  <p class="balisage-option-label"><?= $b['emoji'] ?> <?= $b['label'] ?></p>
                  <p class="balisage-option-desc"><?= $b['desc'] ?></p>
                </div>
              </label>
              <?php endforeach; ?>

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
          <div style="margin-top:.5rem;display:flex;align-items:center;gap:10px;padding:.7rem;background:#FAF8FF;border-radius:8px;border:.5px solid rgba(139,107,177,.15)">
            <img src="/<?= htmlspecialchars($parcours['photo_principale']) ?>"
                 style="width:90px;height:65px;object-fit:cover;border-radius:8px"
                 id="imgPrincipale">
            <div>
              <p style="font-size:12px;color:#5a4870;margin-bottom:.4rem;font-weight:500">Photo principale actuelle</p>
              <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#dc2626;cursor:pointer">
                <input type="checkbox"
                       name="supprimer_photo_principale"
                       value="1"
                       onchange="document.getElementById('imgPrincipale').style.opacity=this.checked?'0.3':'1'">
                Supprimer et remplacer par une nouvelle
              </label>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>Photos galerie (plusieurs possibles)</label>
          <input type="file" name="photos_galerie[]" accept="image/*" multiple>
          <?php if (!empty($parcours['photos_galerie'])): ?>
          <div style="margin-top:.6rem">
            <p style="font-size:11px;color:#9a88b8;margin-bottom:.5rem">
              Photos actuelles — coche ✕ pour supprimer :
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <?php foreach ($parcours['photos_galerie'] as $photo): ?>
              <div style="position:relative;display:inline-block">
                <img src="/<?= htmlspecialchars($photo) ?>"
                     style="width:80px;height:70px;object-fit:cover;border-radius:8px;border:.5px solid rgba(139,107,177,.2);display:block;transition:opacity .2s"
                     id="img-<?= md5($photo) ?>">
                <input type="hidden"
                       name="photos_conserver[]"
                       value="<?= htmlspecialchars($photo) ?>"
                       id="keep-<?= md5($photo) ?>">
                <button type="button"
                        onclick="supprimerPhoto('<?= md5($photo) ?>')"
                        style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#dc2626;border:2px solid #fff;color:#fff;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;line-height:1;padding:0"
                        title="Supprimer cette photo">✕</button>
                <input type="hidden"
                       name="photos_supprimer[]"
                       value=""
                       id="del-<?= md5($photo) ?>">
              </div>
              <?php endforeach; ?>
            </div>
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
      <?php if ($id): ?>
      <div style="margin-top:1rem;padding-top:1rem;border-top:.5px solid #F0E6FF">
        <button type="button" class="btn-card-form" onclick="genererCarte(<?= $id ?>, this)">
          📸 Générer la carte Instagram (1080×1080)
        </button>
        <p style="font-size:11px;color:#9a88b8;margin-top:.4rem">
          Format carré 1080×1080px optimisé pour Instagram
        </p>
      </div>
      <?php endif; ?>
    </form>
  </div>
  <script>
    async function genererCarte(id, btn) {
      btn.classList.add('loading');
      btn.textContent = '⏳ Génération...';
      try {
        const res = await fetch('/admin/generate-card.php?id=' + id);
        const data = await res.json();
        if (data.success && data.cards && data.cards.instagram) {
          const card = data.cards.instagram;
          btn.textContent = '✓ Prête !';
          btn.style.background = '#EAF3DE';
          btn.style.color = '#27500A';
          const existing = document.getElementById('dl-menu-' + id);
          if (existing) existing.remove();
          const a = document.createElement('a');
          a.id = 'dl-menu-' + id;
          a.href = card.url;
          a.download = card.filename;
          a.textContent = '⬇ Télécharger ' + card.label;
          a.style.cssText = 'font-size:11px;padding:4px 12px;border-radius:8px;background:#27500A;color:#fff;text-decoration:none;display:inline-block;margin-top:6px;transition:background .2s';
          btn.parentElement.appendChild(a);
          setTimeout(() => {
            btn.textContent = '📸 Générer la carte Instagram (1080×1080)';
            btn.style.background = '';
            btn.style.color = '';
            btn.classList.remove('loading');
          }, 5000);
        } else {
          alert('Erreur : ' + (data.error || 'Génération impossible'));
          btn.textContent = '📸 Générer la carte Instagram (1080×1080)';
          btn.classList.remove('loading');
        }
      } catch(e) {
        alert('Erreur de connexion');
        btn.textContent = '📸 Générer la carte Instagram (1080×1080)';
        btn.classList.remove('loading');
      }
    }

    function supprimerPhoto(hash) {
      const img = document.getElementById('img-' + hash);
      const del = document.getElementById('del-' + hash);
      if (!img) return;
      if (del.value === '1') {
        del.value = '';
        img.style.opacity = '1';
        img.parentElement.querySelector('button').style.background = '#dc2626';
      } else {
        del.value = '1';
        img.style.opacity = '0.3';
        img.parentElement.querySelector('button').style.background = '#6b7280';
      }
    }
  </script>
</body>
</html>
