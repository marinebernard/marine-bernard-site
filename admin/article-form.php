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

$article = null;
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$form_error = '';

if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
  $stmt->execute([$id]);
  $article = $stmt->fetch();
  if (!$article) { header('Location: /admin/articles.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $photo_principale = $article['photo_principale'] ?? null;

  if (!empty($_POST['supprimer_photo_principale'])) {
    if ($photo_principale) {
      $full = __DIR__ . '/../' . $photo_principale;
      if (file_exists($full)) @unlink($full);
    }
    $photo_principale = null;
  }
  if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] === UPLOAD_ERR_OK) {
    $new = uploadImage($_FILES['photo_principale'], 'articles');
    if ($new) $photo_principale = $new;
  }

  $titre = trim($_POST['titre'] ?? '');
  $slug  = trim($_POST['slug'] ?? '');
  if (empty($slug)) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $titre)));
    $slug = trim($slug, '-');
  }

  $data = [
    'titre'             => $titre,
    'slug'              => $slug,
    'categorie'         => $_POST['categorie'] ?? 'randonnee',
    'photo_principale'  => $photo_principale,
    'extrait'           => trim($_POST['extrait'] ?? ''),
    'contenu'           => $_POST['contenu'] ?? '',
    'visible'           => isset($_POST['visible']) ? 1 : 0,
    'date_publication'  => !empty($_POST['date_publication']) ? $_POST['date_publication'] : date('Y-m-d'),
    'temps_lecture'     => !empty($_POST['temps_lecture']) ? (int)$_POST['temps_lecture'] : null,
  ];

  try {
    if ($id) {
      $data['id'] = $id;
      $stmt = $pdo->prepare("UPDATE articles SET titre=:titre, slug=:slug, categorie=:categorie, photo_principale=:photo_principale, extrait=:extrait, contenu=:contenu, visible=:visible, date_publication=:date_publication, temps_lecture=:temps_lecture WHERE id=:id");
    } else {
      $stmt = $pdo->prepare("INSERT INTO articles (titre,slug,categorie,photo_principale,extrait,contenu,visible,date_publication,temps_lecture) VALUES (:titre,:slug,:categorie,:photo_principale,:extrait,:contenu,:visible,:date_publication,:temps_lecture)");
    }
    $stmt->execute($data);
    header('Location: /admin/articles.php?saved=1');
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
  <title><?= $id ? 'Modifier' : 'Nouvel' ?> article — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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
    input[type=text],input[type=number],input[type=date],input[type=url],select,textarea{border:.5px solid rgba(139,107,177,.3);border-radius:10px;padding:9px 12px;font-size:13px;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;width:100%;background:#fff}
    input:focus,select:focus,textarea:focus{border-color:#8B6BB1}
    textarea.contenu{min-height:400px;resize:vertical;font-family:'Georgia',serif;font-size:14px;line-height:1.8}
    .checkbox-group{display:flex;align-items:center;gap:8px;font-size:13px;color:#2D1B69}
    .checkbox-group input{width:auto}
    .upload-preview img{width:100%;max-height:180px;object-fit:cover;border-radius:10px;margin-top:.5rem;border:.5px solid rgba(139,107,177,.2)}
    .form-hint{font-size:11px;color:#9a88b8;margin-top:.3rem;line-height:1.5}
    .btn-submit{background:#2D1B69;color:#fff;border:none;border-radius:22px;padding:12px 28px;font-size:14px;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s}
    .btn-submit:hover{background:#8B6BB1}
    .btn-cancel{background:#F0E6FF;color:#2D1B69;border:none;border-radius:22px;padding:12px 20px;font-size:14px;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;display:inline-block}
    .form-actions{display:flex;gap:10px;margin-top:1.5rem}
    .error{background:#fef2f2;color:#dc2626;border-radius:8px;padding:.6rem 1rem;font-size:13px;margin-bottom:1rem}
    .toolbar{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:.5rem}
    .toolbar-btn{font-size:11px;padding:4px 8px;border-radius:6px;border:.5px solid rgba(139,107,177,.2);background:#FAF8FF;color:#5a4870;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}
    .toolbar-btn:hover{background:#F0E6FF;color:#2D1B69}
  </style>
</head>
<body>
  <header class="admin-header">
    <span class="admin-logo">Marine Bernard ✿</span>
    <nav class="admin-nav">
      <a href="/admin/articles.php">← Articles</a>
    </nav>
  </header>
  <div class="content">
    <h1 class="page-title"><?= $id ? 'Modifier l\'article' : 'Nouvel article' ?></h1>
    <?php if ($form_error): ?><div class="error"><?= htmlspecialchars($form_error) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">

      <div class="form-section">
        <p class="form-section-title">Informations générales</p>
        <div class="form-grid">
          <div class="form-group full">
            <label>Titre de l'article *</label>
            <input type="text" name="titre" value="<?= htmlspecialchars($article['titre'] ?? '') ?>" required placeholder="Ex: Comprendre les balisages de randonnée" oninput="genSlug(this.value)">
          </div>
          <div class="form-group full">
            <label>Slug URL (généré automatiquement)</label>
            <input type="text" name="slug" id="slugInput" value="<?= htmlspecialchars($article['slug'] ?? '') ?>" placeholder="comprendre-les-balisages">
            <p class="form-hint">URL finale : /pages/blog-article.html?slug=<span id="slugPreview"><?= htmlspecialchars($article['slug'] ?? 'votre-slug') ?></span></p>
          </div>
          <div class="form-group">
            <label>Catégorie</label>
            <select name="categorie">
              <?php foreach (['randonnee' => 'Randonnée', 'photo' => 'Photo nature', 'conseils' => 'Conseils', 'equipement' => 'Équipement', 'destination' => 'Destination'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($article['categorie'] ?? 'randonnee') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Date de publication</label>
            <input type="date" name="date_publication" value="<?= $article['date_publication'] ?? date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>Temps de lecture (minutes)</label>
            <input type="number" name="temps_lecture" min="1" max="60" value="<?= $article['temps_lecture'] ?? '' ?>" placeholder="5">
          </div>
          <div class="form-group" style="justify-content:flex-end">
            <div class="checkbox-group">
              <input type="checkbox" name="visible" id="visible" <?= ($article['visible'] ?? 1) ? 'checked' : '' ?>>
              <label for="visible">Visible sur le site</label>
            </div>
          </div>
          <div class="form-group full">
            <label>Extrait (affiché sur la page blog)</label>
            <textarea name="extrait" rows="3" placeholder="Résumé court de l'article — affiché sur la page liste du blog"><?= htmlspecialchars($article['extrait'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <div class="form-section">
        <p class="form-section-title">Photo principale</p>
        <div class="form-group">
          <input type="file" name="photo_principale" accept="image/*" onchange="previewImg(this)">
          <p class="form-hint">Format recommandé : 1200×630px, JPG ou WebP, moins de 2 Mo</p>
          <p style="font-size:11px;color:#9a88b8;margin-top:.3rem">
            ✓ Optimisation automatique — toutes les images sont redimensionnées (max 1600px) et compressées en JPG 82% à l'upload
          </p>
          <?php if (!empty($article['photo_principale'])): ?>
          <div class="upload-preview">
            <img src="/<?= htmlspecialchars($article['photo_principale']) ?>" id="imgPrincipale" alt="">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#dc2626;cursor:pointer;margin-top:.4rem">
              <input type="checkbox" name="supprimer_photo_principale" value="1" onchange="document.getElementById('imgPrincipale').style.opacity=this.checked?'0.3':'1'">
              Supprimer cette photo
            </label>
          </div>
          <?php endif; ?>
          <div id="imgPreview" style="display:none" class="upload-preview"><img id="imgPreviewSrc" src="" alt=""></div>
        </div>
      </div>

      <div class="form-section">
        <p class="form-section-title">Contenu de l'article</p>
        <div class="toolbar">
          <button type="button" class="toolbar-btn" onclick="insert('## ', '')">H2</button>
          <button type="button" class="toolbar-btn" onclick="insert('### ', '')">H3</button>
          <button type="button" class="toolbar-btn" onclick="insert('**', '**')"><strong>B</strong></button>
          <button type="button" class="toolbar-btn" onclick="insert('*', '*')"><em>I</em></button>
          <button type="button" class="toolbar-btn" onclick="insert('\n✦ ', '')">✦ Liste</button>
          <button type="button" class="toolbar-btn" onclick="insert('\n> ', '')">Citation</button>
          <button type="button" class="toolbar-btn" onclick="insert('\n---\n', '')">Séparateur</button>
        </div>
        <textarea name="contenu" id="contenuArea" class="contenu" placeholder="Rédige ton article ici...

## Titre de section

Paragraphe de texte...

✦ Point important
✦ Autre point

> Citation ou mise en avant"><?= htmlspecialchars($article['contenu'] ?? '') ?></textarea>
        <p class="form-hint">Utilise ## pour les titres H2, ### pour H3, **texte** pour le gras, *texte* pour l'italique, ✦ pour les listes.</p>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn-submit">Enregistrer l'article</button>
        <a href="/admin/articles.php" class="btn-cancel">Annuler</a>
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

  function previewImg(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => {
        document.getElementById('imgPreviewSrc').src = e.target.result;
        document.getElementById('imgPreview').style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function insert(before, after) {
    const ta = document.getElementById('contenuArea');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const sel = ta.value.substring(start, end);
    ta.value = ta.value.substring(0, start) + before + sel + after + ta.value.substring(end);
    ta.focus();
    ta.setSelectionRange(start + before.length, start + before.length + sel.length);
  }
  </script>
</body>
</html>
