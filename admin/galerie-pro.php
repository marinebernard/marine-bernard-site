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

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
  $stmt = $pdo->prepare("SELECT photo FROM galerie_pro WHERE id = ?");
  $stmt->execute([(int)$_GET['delete']]);
  $g = $stmt->fetch();
  if ($g && $g['photo'] && file_exists(__DIR__ . '/../' . $g['photo'])) @unlink(__DIR__ . '/../' . $g['photo']);
  $pdo->prepare("DELETE FROM galerie_pro WHERE id = ?")->execute([(int)$_GET['delete']]);
  header('Location: /admin/galerie-pro.php?deleted=1');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'add') {
    if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
      $uploaded = uploadMultipleImages($_FILES['photos'], 'galerie-pro');
      foreach ($uploaded as $path) {
        $stmt = $pdo->prepare("INSERT INTO galerie_pro (titre,description,photo,categorie,ordre,visible) VALUES (?,?,?,?,?,1)");
        $stmt->execute([trim($_POST['titre'] ?? ''), trim($_POST['description'] ?? ''), $path, $_POST['categorie'] ?? 'photo', (int)($_POST['ordre'] ?? 0)]);
      }
      header('Location: /admin/galerie-pro.php?saved=1');
      exit;
    }
  }
  if ($_POST['action'] === 'toggle' && isset($_POST['id'])) {
    $pdo->prepare("UPDATE galerie_pro SET visible = 1 - visible WHERE id = ?")->execute([(int)$_POST['id']]);
    header('Location: /admin/galerie-pro.php');
    exit;
  }
}

$photos = $pdo->query("SELECT * FROM galerie_pro ORDER BY ordre ASC, date_creation DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Galerie pro — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%232D1B69'/><circle cx='16' cy='16' r='4' fill='%23FAF8FF'/><ellipse cx='16' cy='7' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='16' cy='25' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='7' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='25' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='9.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 9.5 9.5)'/><ellipse cx='22.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 22.5 9.5)'/><ellipse cx='9.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 9.5 22.5)'/><ellipse cx='22.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 22.5 22.5)'/><circle cx='16' cy='16' r='2.5' fill='%232D1B69'/></svg>">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;color:#2D1B69;min-height:100vh}
    .admin-header{background:#2D1B69;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
    .admin-logo{font-family:'Playfair Display',serif;font-style:italic;font-size:15px;color:#fff}
    .admin-nav{display:flex;align-items:center;gap:12px}
    .admin-nav a{font-size:12px;color:rgba(255,255,255,.7);text-decoration:none}
    .admin-nav a.active{color:#fff;font-weight:500}
    .content{padding:2rem;max-width:1100px;margin:0 auto}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem}
    .page-title{font-family:'Playfair Display',serif;font-size:20px;color:#2D1B69}
    .success{background:#EAF3DE;color:#27500A;border-radius:8px;padding:.6rem 1rem;font-size:13px;margin-bottom:1rem}
    .upload-section{background:#fff;border:.5px solid rgba(139,107,177,.15);border-radius:14px;padding:1.3rem;margin-bottom:1.5rem}
    .upload-section-title{font-size:11px;font-weight:600;color:#8B6BB1;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:.5px solid #F0E6FF}
    .upload-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.8rem;align-items:end}
    label{font-size:12px;font-weight:500;color:#5a4870;display:block;margin-bottom:.3rem}
    input[type=text],select{border:.5px solid rgba(139,107,177,.3);border-radius:10px;padding:9px 12px;font-size:13px;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;width:100%;background:#fff}
    input:focus,select:focus{border-color:#8B6BB1}
    .upload-zone{border:1.5px dashed rgba(139,107,177,.35);border-radius:10px;padding:1.2rem;text-align:center;cursor:pointer;transition:all .2s;background:#FAF8FF;grid-column:1/-1}
    .upload-zone:hover{border-color:#8B6BB1;background:#F0E6FF}
    .upload-zone input{display:none}
    .upload-zone-text{font-size:13px;color:#9a88b8}
    .upload-zone-text strong{color:#2D1B69;display:block;margin-bottom:.3rem}
    .btn-upload{background:#2D1B69;color:#fff;border:none;border-radius:22px;padding:10px 22px;font-size:13px;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s;white-space:nowrap}
    .btn-upload:hover{background:#8B6BB1}
    .gallery-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
    .gallery-item{position:relative;border-radius:12px;overflow:hidden;aspect-ratio:4/3;background:#F0E6FF}
    .gallery-item img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s}
    .gallery-item:hover img{transform:scale(1.05)}
    .gallery-item-overlay{position:absolute;inset:0;background:rgba(45,27,105,0);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;opacity:0;transition:all .25s}
    .gallery-item:hover .gallery-item-overlay{background:rgba(45,27,105,.65);opacity:1}
    .gallery-item-btn{font-size:11px;font-weight:500;padding:5px 12px;border-radius:16px;border:none;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}
    .btn-vis{background:rgba(255,255,255,.2);color:#fff;border:.5px solid rgba(255,255,255,.3)}
    .btn-vis:hover{background:rgba(255,255,255,.35)}
    .btn-del-g{background:rgba(220,38,38,.8);color:#fff}
    .btn-del-g:hover{background:rgba(220,38,38,1)}
    .gallery-item.hidden-item{opacity:.4}
    .gallery-item-label{position:absolute;bottom:0;left:0;right:0;padding:.4rem .6rem;background:rgba(45,27,105,.7);color:#fff;font-size:11px;transform:translateY(100%);transition:transform .25s}
    .gallery-item:hover .gallery-item-label{transform:translateY(0)}
    .empty-state{text-align:center;padding:3rem;color:#9a88b8;font-size:13px;font-style:italic;background:#fff;border-radius:14px;border:.5px solid rgba(139,107,177,.15)}
    @media(max-width:900px){.gallery-grid{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>
  <header class="admin-header">
    <span class="admin-logo">Marine Bernard ✿</span>
    <nav class="admin-nav">
      <a href="/admin/index.php">Parcours</a>
      <a href="/admin/articles.php">Articles</a>
      <a href="/admin/projets.php">Projets</a>
      <a href="/admin/galerie-pro.php" class="active">Galerie pro</a>
      <a href="/" target="_blank">Voir le site ↗</a>
      <a href="/admin/login.php?logout=1" style="background:rgba(255,255,255,.1);border:.5px solid rgba(255,255,255,.25);border-radius:15px;padding:4px 12px;color:#fff">Déconnexion</a>
    </nav>
  </header>
  <div class="content">
    <?php if (isset($_GET['deleted'])): ?><div class="success">✓ Photo supprimée.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="success">✓ Photo(s) ajoutée(s).</div><?php endif; ?>
    <div class="page-header">
      <h1 class="page-title">Galerie photo pro <span style="font-size:14px;color:#9a88b8;font-family:'Inter',sans-serif">(<?= count($photos) ?> photos)</span></h1>
    </div>

    <div class="upload-section">
      <p class="upload-section-title">Ajouter des photos</p>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="upload-grid">
          <div>
            <label>Titre (optionnel)</label>
            <input type="text" name="titre" placeholder="Ex: Bois de Saint-Pierre">
          </div>
          <div>
            <label>Catégorie</label>
            <select name="categorie">
              <option value="photo">Photo</option>
              <option value="graphisme">Graphisme</option>
              <option value="uxui">UX/UI</option>
              <option value="print">Print</option>
            </select>
          </div>
          <div>
            <label>Description courte</label>
            <input type="text" name="description" placeholder="Légende de la photo">
          </div>
        </div>
        <div style="margin-top:.8rem">
          <label class="upload-zone" for="photosInput">
            <input type="file" name="photos[]" id="photosInput" accept="image/*" multiple onchange="previewFiles(this)">
            <div class="upload-zone-text">
              <strong>📷 Cliquer pour sélectionner des photos</strong>
              Plusieurs photos possibles — JPG, PNG, WebP — optimisées automatiquement
            </div>
            <div id="filesPreview" style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center;margin-top:.8rem"></div>
          </label>
        </div>
        <div style="text-align:right;margin-top:.8rem">
          <button type="submit" class="btn-upload">⬆ Uploader les photos</button>
        </div>
      </form>
    </div>

    <?php if (empty($photos)): ?>
    <div class="empty-state">Aucune photo dans la galerie pro — ajoute-en ci-dessus !</div>
    <?php else: ?>
    <div class="gallery-grid">
      <?php foreach ($photos as $g): ?>
      <div class="gallery-item <?= !$g['visible'] ? 'hidden-item' : '' ?>">
        <img src="/<?= htmlspecialchars($g['photo']) ?>" alt="<?= htmlspecialchars($g['titre'] ?? '') ?>" loading="lazy">
        <div class="gallery-item-overlay">
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $g['id'] ?>">
            <button type="submit" class="gallery-item-btn btn-vis"><?= $g['visible'] ? '👁 Masquer' : '👁 Afficher' ?></button>
          </form>
          <button class="gallery-item-btn btn-del-g" onclick="if(confirm('Supprimer cette photo ?')) window.location='/admin/galerie-pro.php?delete=<?= $g['id'] ?>'">✕ Supprimer</button>
        </div>
        <?php if ($g['titre'] || $g['description']): ?>
        <div class="gallery-item-label">
          <?= htmlspecialchars($g['titre'] ?: $g['description'] ?: '') ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <script>
  function previewFiles(input) {
    const preview = document.getElementById('filesPreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(file => {
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.cssText = 'width:70px;height:55px;object-fit:cover;border-radius:8px;border:.5px solid rgba(139,107,177,.3)';
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  }
  </script>
</body>
</html>
