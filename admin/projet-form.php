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

$projet = null;
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$form_error = '';

if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM projets WHERE id = ?");
  $stmt->execute([$id]);
  $projet = $stmt->fetch();
  if (!$projet) { header('Location: /admin/projets.php'); exit; }
  $projet['photos_galerie'] = json_decode($projet['photos_galerie'] ?? '[]', true) ?: [];
  $projet['outils'] = json_decode($projet['outils'] ?? '[]', true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $photo_principale = $projet['photo_principale'] ?? null;
  if (!empty($_POST['supprimer_photo_principale'])) {
    if ($photo_principale && file_exists(__DIR__ . '/../' . $photo_principale)) @unlink(__DIR__ . '/../' . $photo_principale);
    $photo_principale = null;
  }
  if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] === UPLOAD_ERR_OK) {
    $new = uploadImage($_FILES['photo_principale'], 'projets');
    if ($new) $photo_principale = $new;
  }

  $photos_conserver = $_POST['photos_conserver'] ?? [];
  $photos_supprimer = $_POST['photos_supprimer'] ?? [];
  $photos_galerie = [];
  foreach ($photos_conserver as $i => $photo) {
    if (!empty($photo)) {
      if (isset($photos_supprimer[$i]) && $photos_supprimer[$i] === '1') {
        $full = __DIR__ . '/../' . $photo;
        if (file_exists($full)) @unlink($full);
      } else {
        $photos_galerie[] = $photo;
      }
    }
  }
  if (isset($_FILES['photos_galerie']) && !empty($_FILES['photos_galerie']['name'][0])) {
    $nouvelles = uploadMultipleImages($_FILES['photos_galerie'], 'projets');
    $photos_galerie = array_merge($photos_galerie, $nouvelles);
  }

  $titre = trim($_POST['titre'] ?? '');
  $slug = trim($_POST['slug'] ?? '');
  if (empty($slug)) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $titre)));
    $slug = trim($slug, '-');
  }

  $outils = array_filter(array_map('trim', explode(',', $_POST['outils'] ?? '')));

  $data = [
    'titre'            => $titre,
    'slug'             => $slug,
    'categorie'        => $_POST['categorie'] ?? 'web',
    'client'           => trim($_POST['client'] ?? ''),
    'annee'            => !empty($_POST['annee']) ? (int)$_POST['annee'] : null,
    'description'      => trim($_POST['description'] ?? ''),
    'mission'          => trim($_POST['mission'] ?? ''),
    'outils'           => json_encode(array_values($outils)),
    'url_site'         => trim($_POST['url_site'] ?? ''),
    'photo_principale' => $photo_principale,
    'photos_galerie'   => json_encode(array_values($photos_galerie)),
    'visible'          => isset($_POST['visible']) ? 1 : 0,
    'ordre'            => (int)($_POST['ordre'] ?? 0),
  ];

  try {
    if ($id) {
      $data['id'] = $id;
      $stmt = $pdo->prepare("UPDATE projets SET titre=:titre, slug=:slug, categorie=:categorie, client=:client, annee=:annee, description=:description, mission=:mission, outils=:outils, url_site=:url_site, photo_principale=:photo_principale, photos_galerie=:photos_galerie, visible=:visible, ordre=:ordre WHERE id=:id");
    } else {
      $stmt = $pdo->prepare("INSERT INTO projets (titre,slug,categorie,client,annee,description,mission,outils,url_site,photo_principale,photos_galerie,visible,ordre) VALUES (:titre,:slug,:categorie,:client,:annee,:description,:mission,:outils,:url_site,:photo_principale,:photos_galerie,:visible,:ordre)");
    }
    $stmt->execute($data);
    header('Location: /admin/projets.php?saved=1');
    exit;
  } catch(PDOException $e) {
    $form_error = 'Erreur : ' . $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title><?= $id ? 'Modifier' : 'Nouveau' ?> projet — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%232D1B69'/><circle cx='16' cy='16' r='4' fill='%23FAF8FF'/><ellipse cx='16' cy='7' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='16' cy='25' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='7' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='25' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='9.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 9.5 9.5)'/><ellipse cx='22.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 22.5 9.5)'/><ellipse cx='9.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 9.5 22.5)'/><ellipse cx='22.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 22.5 22.5)'/><circle cx='16' cy='16' r='2.5' fill='%232D1B69'/></svg>">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;color:#2D1B69;min-height:100vh}
    .admin-header{background:#2D1B69;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
    .admin-logo{font-family:'Playfair Display',serif;font-style:italic;font-size:15px;color:#fff}
    .admin-nav a{font-size:12px;color:rgba(255,255,255,.7);text-decoration:none}
    .content{padding:2rem;max-width:900px;margin:0 auto}
    .page-title{font-family:'Playfair Display',serif;font-size:22px;color:#2D1B69;margin-bottom:1.5rem}
    .form-section{background:#fff;border:.5px solid rgba(139,107,177,.15);border-radius:14px;padding:1.2rem;margin-bottom:1rem}
    .form-section-title{font-size:11px;font-weight:600;color:#8B6BB1;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:.5px solid #F0E6FF}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .form-group{display:flex;flex-direction:column;gap:.4rem;margin-bottom:.6rem}
    .form-group.full{grid-column:1/3}
    label{font-size:12px;font-weight:500;color:#5a4870}
    input[type=text],input[type=number],input[type=url],input[type=date],select,textarea{border:.5px solid rgba(139,107,177,.3);border-radius:10px;padding:9px 12px;font-size:13px;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;width:100%;background:#fff}
    input:focus,select:focus,textarea:focus{border-color:#8B6BB1}
    textarea{resize:vertical;min-height:100px}
    .checkbox-group{display:flex;align-items:center;gap:8px;font-size:13px;color:#2D1B69}
    .checkbox-group input{width:auto}
    .photo-del-wrap{position:relative;display:inline-block}
    .photo-del-btn{position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#dc2626;border:2px solid #fff;color:#fff;font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;line-height:1;padding:0}
    .form-hint{font-size:11px;color:#9a88b8;margin-top:.3rem;line-height:1.5}
    .btn-submit{background:#2D1B69;color:#fff;border:none;border-radius:22px;padding:12px 28px;font-size:14px;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s}
    .btn-submit:hover{background:#8B6BB1}
    .btn-cancel{background:#F0E6FF;color:#2D1B69;border:none;border-radius:22px;padding:12px 20px;font-size:14px;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;display:inline-block}
    .form-actions{display:flex;gap:10px;margin-top:1.5rem}
    .error{background:#fef2f2;color:#dc2626;border-radius:8px;padding:.6rem 1rem;font-size:13px;margin-bottom:1rem}
  </style>
</head>
<body>
  <header class="admin-header">
    <span class="admin-logo">Marine Bernard ✿</span>
    <nav class="admin-nav">
      <a href="/admin/projets.php">← Projets</a>
    </nav>
  </header>
  <div class="content">
    <h1 class="page-title"><?= $id ? 'Modifier le projet' : 'Nouveau projet' ?></h1>
    <?php if ($form_error): ?><div class="error"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">

      <div class="form-section">
        <p class="form-section-title">Informations générales</p>
        <div class="form-grid">
          <div class="form-group full">
            <label>Titre du projet *</label>
            <input type="text" name="titre" value="<?= htmlspecialchars($projet['titre'] ?? '') ?>" required placeholder="Ex: Refonte site Jouvence" oninput="genSlug(this.value)">
          </div>
          <div class="form-group full">
            <label>Slug URL</label>
            <input type="text" name="slug" id="slugInput" value="<?= htmlspecialchars($projet['slug'] ?? '') ?>" placeholder="refonte-jouvence">
            <p class="form-hint">URL : /pages/<span id="slugPreview"><?= htmlspecialchars($projet['slug'] ?? 'votre-slug') ?></span>.html</p>
          </div>
          <div class="form-group">
            <label>Client / Entreprise</label>
            <input type="text" name="client" value="<?= htmlspecialchars($projet['client'] ?? '') ?>" placeholder="Ex: Jouvence">
          </div>
          <div class="form-group">
            <label>Année</label>
            <input type="number" name="annee" min="2015" max="2030" value="<?= $projet['annee'] ?? date('Y') ?>" placeholder="<?= date('Y') ?>">
          </div>
          <div class="form-group">
            <label>Catégorie</label>
            <select name="categorie">
              <?php
              $cats = [
                'web'       => 'UX/UI — Web',
                'ecommerce' => 'E-commerce',
                'print'     => 'Graphisme / Print',
                'logo'      => 'Identité visuelle / Logo',
                'community' => 'Community management',
                'photo'     => 'Photo',
              ];
              foreach ($cats as $val => $label):
              ?>
              <option value="<?= $val ?>" <?= ($projet['categorie'] ?? 'web') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>URL du site (optionnel)</label>
            <input type="url" name="url_site" value="<?= htmlspecialchars($projet['url_site'] ?? '') ?>" placeholder="https://www.jouvence.fr">
          </div>
          <div class="form-group">
            <label>Ordre d'affichage</label>
            <input type="number" name="ordre" min="0" value="<?= $projet['ordre'] ?? 0 ?>" placeholder="0">
            <p class="form-hint">0 = en premier. Plus le chiffre est grand, plus le projet est affiché tard.</p>
          </div>
          <div class="form-group" style="justify-content:flex-end">
            <div class="checkbox-group">
              <input type="checkbox" name="visible" id="visible" <?= ($projet['visible'] ?? 1) ? 'checked' : '' ?>>
              <label for="visible">Visible sur le site</label>
            </div>
          </div>
          <div class="form-group full">
            <label>Description du projet</label>
            <textarea name="description" rows="4" placeholder="Présentation du projet — contexte, objectifs, résultats..."><?= htmlspecialchars($projet['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Ma mission / Ce que j'ai fait</label>
            <textarea name="mission" rows="4" placeholder="UX/UI, refonte charte graphique, intégration HTML/CSS, création des visuels..."><?= htmlspecialchars($projet['mission'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Outils utilisés (séparés par des virgules)</label>
            <input type="text" name="outils" value="<?= htmlspecialchars(implode(', ', $projet['outils'] ?? [])) ?>" placeholder="Figma, Photoshop, WordPress, PrestaShop">
          </div>
        </div>
      </div>

      <div class="form-section">
        <p class="form-section-title">Photos</p>
        <div class="form-group">
          <label>Photo principale (mockup / visuel du projet)</label>
          <input type="file" name="photo_principale" accept="image/*" onchange="previewMain(this)">
          <p class="form-hint">✓ Optimisée automatiquement — max 1600px, JPG 82%</p>
          <?php if (!empty($projet['photo_principale'])): ?>
          <div style="margin-top:.5rem;display:flex;align-items:center;gap:10px;padding:.7rem;background:#FAF8FF;border-radius:8px;border:.5px solid rgba(139,107,177,.15)">
            <img src="/<?= htmlspecialchars($projet['photo_principale']) ?>" id="imgPrincipale" style="width:100px;height:70px;object-fit:cover;border-radius:8px" alt="">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#dc2626;cursor:pointer">
              <input type="checkbox" name="supprimer_photo_principale" value="1" onchange="document.getElementById('imgPrincipale').style.opacity=this.checked?'0.3':'1'">
              Supprimer et remplacer
            </label>
          </div>
          <?php endif; ?>
          <div id="mainPreview" style="display:none;margin-top:.5rem"><img id="mainPreviewImg" src="" style="max-width:200px;height:120px;object-fit:cover;border-radius:8px" alt=""></div>
        </div>
        <div class="form-group">
          <label>Photos galerie (captures d'écran, détails, process...)</label>
          <input type="file" name="photos_galerie[]" accept="image/*" multiple>
          <p class="form-hint">✓ Plusieurs fichiers possibles — toutes optimisées automatiquement</p>
          <?php if (!empty($projet['photos_galerie'])): ?>
          <div style="margin-top:.6rem">
            <p style="font-size:11px;color:#9a88b8;margin-bottom:.5rem">Photos actuelles — coche ✕ pour supprimer :</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <?php foreach ($projet['photos_galerie'] as $photo): ?>
              <div class="photo-del-wrap">
                <img src="/<?= htmlspecialchars($photo) ?>" style="width:80px;height:60px;object-fit:cover;border-radius:8px;border:.5px solid rgba(139,107,177,.2);display:block;transition:opacity .2s" id="img-<?= md5($photo) ?>">
                <input type="hidden" name="photos_conserver[]" value="<?= htmlspecialchars($photo) ?>" id="keep-<?= md5($photo) ?>">
                <input type="hidden" name="photos_supprimer[]" value="" id="del-<?= md5($photo) ?>">
                <button type="button" class="photo-del-btn" onclick="supprimerPhoto('<?= md5($photo) ?>')">✕</button>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-submit">Enregistrer le projet</button>
        <a href="/admin/projets.php" class="btn-cancel">Annuler</a>
      </div>
    </form>
  </div>
  <script>
  function genSlug(titre) {
    const combiningDiacritics = new RegExp('[̀-ͯ]', 'g');
    const slug = titre.toLowerCase()
      .normalize('NFD').replace(combiningDiacritics, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
    document.getElementById('slugInput').value = slug;
    document.getElementById('slugPreview').textContent = slug || 'votre-slug';
  }
  function previewMain(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => { document.getElementById('mainPreviewImg').src = e.target.result; document.getElementById('mainPreview').style.display = 'block'; };
      reader.readAsDataURL(input.files[0]);
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
