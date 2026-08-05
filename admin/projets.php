<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../api/config.php';
requireLogin();

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
  $stmt = $pdo->prepare("SELECT photo_principale, photos_galerie FROM projets WHERE id = ?");
  $stmt->execute([(int)$_GET['delete']]);
  $proj = $stmt->fetch();
  if ($proj) {
    if ($proj['photo_principale'] && file_exists(__DIR__ . '/../' . $proj['photo_principale'])) {
      @unlink(__DIR__ . '/../' . $proj['photo_principale']);
    }
    $galerie = json_decode($proj['photos_galerie'] ?? '[]', true);
    foreach ($galerie as $photo) {
      if (file_exists(__DIR__ . '/../' . $photo)) @unlink(__DIR__ . '/../' . $photo);
    }
    $pdo->prepare("DELETE FROM projets WHERE id = ?")->execute([(int)$_GET['delete']]);
  }
  header('Location: /admin/projets.php?deleted=1');
  exit;
}

$projets = $pdo->query("SELECT * FROM projets ORDER BY ordre ASC, date_creation DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Projets — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%232D1B69'/><circle cx='16' cy='16' r='4' fill='%23FAF8FF'/><ellipse cx='16' cy='7' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='16' cy='25' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='7' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='25' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='9.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 9.5 9.5)'/><ellipse cx='22.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 22.5 9.5)'/><ellipse cx='9.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 9.5 22.5)'/><ellipse cx='22.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 22.5 22.5)'/><circle cx='16' cy='16' r='2.5' fill='%232D1B69'/></svg>">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;color:#2D1B69;min-height:100vh}
    .admin-header{background:#2D1B69;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
    .admin-logo{font-family:'Playfair Display',serif;font-style:italic;font-size:15px;color:#fff}
    .admin-nav{display:flex;align-items:center;gap:12px}
    .admin-nav a{font-size:12px;color:rgba(255,255,255,.7);text-decoration:none;transition:color .2s}
    .admin-nav a:hover,.admin-nav a.active{color:#fff}
    .btn-add{display:inline-flex;align-items:center;gap:5px;background:#C9A96E;color:#2D1B69;border:none;border-radius:20px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;font-family:'Inter',sans-serif}
    .content{padding:2rem}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem}
    .page-title{font-family:'Playfair Display',serif;font-size:20px;color:#2D1B69}
    .success{background:#EAF3DE;color:#27500A;border-radius:8px;padding:.6rem 1rem;font-size:13px;margin-bottom:1rem}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;border:.5px solid rgba(139,107,177,.15)}
    th{background:#F0E6FF;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8B6BB1;padding:.7rem 1rem;text-align:left;font-weight:500}
    td{padding:.8rem 1rem;font-size:13px;border-top:.5px solid rgba(139,107,177,.08);vertical-align:middle}
    tr:hover td{background:#FAF8FF}
    .badge-cat{font-size:10px;padding:2px 8px;border-radius:8px;font-weight:500}
    .cat-web{background:#EAF3DE;color:#27500A}
    .cat-print{background:#FAEEDA;color:#633806}
    .cat-logo{background:#EEEDFE;color:#3C3489}
    .cat-photo{background:#F0E6FF;color:#2D1B69}
    .cat-ecommerce{background:#E6F4FF;color:#0C447C}
    .cat-community{background:#FEF3E6;color:#7A3B00}
    .photo-thumb{width:50px;height:40px;object-fit:cover;border-radius:6px}
    .no-photo{width:50px;height:40px;background:#F0E6FF;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:1rem}
    .td-actions{display:flex;gap:5px;flex-wrap:wrap}
    .btn-edit{font-size:11px;padding:4px 10px;border-radius:8px;background:#F0E6FF;color:#2D1B69;border:none;cursor:pointer;text-decoration:none;transition:background .2s}
    .btn-edit:hover{background:#d4b8f0}
    .btn-view{font-size:11px;padding:4px 10px;border-radius:8px;background:#EAF3DE;color:#27500A;border:none;cursor:pointer;text-decoration:none;transition:background .2s}
    .btn-del{font-size:11px;padding:4px 10px;border-radius:8px;background:#fef2f2;color:#dc2626;border:none;cursor:pointer;transition:background .2s}
    .btn-del:hover{background:#fee2e2}
  </style>
</head>
<body>
  <header class="admin-header">
    <span class="admin-logo">Marine Bernard ✿</span>
    <nav class="admin-nav">
      <a href="/admin/index.php">Parcours</a>
      <a href="/admin/articles.php">Articles</a>
      <a href="/admin/projets.php" class="active" style="color:#fff;font-weight:500">Projets</a>
      <a href="/admin/galerie-pro.php">Galerie pro</a>
      <a href="/" target="_blank">Voir le site ↗</a>
      <a href="/admin/login.php?logout=1" style="background:rgba(255,255,255,.1);border:.5px solid rgba(255,255,255,.25);border-radius:15px;padding:4px 12px;color:#fff">Déconnexion</a>
    </nav>
  </header>
  <div class="content">
    <?php if (isset($_GET['deleted'])): ?><div class="success">✓ Projet supprimé.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="success">✓ Projet enregistré.</div><?php endif; ?>
    <div class="page-header">
      <h1 class="page-title">Projets pro <span style="font-size:14px;color:#9a88b8;font-family:'Inter',sans-serif">(<?= count($projets) ?>)</span></h1>
      <a href="/admin/projet-form.php" class="btn-add">➕ Nouveau projet</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>Photo</th>
          <th>Titre</th>
          <th>Client</th>
          <th>Catégorie</th>
          <th>Année</th>
          <th>Ordre</th>
          <th>Visible</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($projets as $proj): ?>
        <tr>
          <td>
            <?php if ($proj['photo_principale']): ?>
              <img src="/<?= htmlspecialchars($proj['photo_principale']) ?>" class="photo-thumb" alt="">
            <?php else: ?>
              <div class="no-photo">🎨</div>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= htmlspecialchars($proj['titre']) ?></strong>
            <?php if ($proj['url_site']): ?>
            <br><a href="<?= htmlspecialchars($proj['url_site']) ?>" target="_blank" style="font-size:11px;color:#8B6BB1">↗ <?= htmlspecialchars($proj['url_site']) ?></a>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#7a6090"><?= htmlspecialchars($proj['client'] ?? '—') ?></td>
          <td><span class="badge-cat cat-<?= htmlspecialchars($proj['categorie']) ?>"><?= ucfirst($proj['categorie']) ?></span></td>
          <td style="font-size:12px;color:#7a6090"><?= $proj['annee'] ?? '—' ?></td>
          <td style="font-size:12px;color:#7a6090"><?= $proj['ordre'] ?></td>
          <td><?= $proj['visible'] ? '✓' : '<span class="badge-cat" style="background:#f3f4f6;color:#9ca3af">Masqué</span>' ?></td>
          <td>
            <div class="td-actions">
              <a href="/admin/projet-form.php?id=<?= $proj['id'] ?>" class="btn-edit">Modifier</a>
              <?php if ($proj['url_site']): ?>
              <a href="<?= htmlspecialchars($proj['url_site']) ?>" target="_blank" class="btn-view">Voir</a>
              <?php endif; ?>
              <button class="btn-del" onclick="if(confirm('Supprimer ce projet ?')) window.location='/admin/projets.php?delete=<?= $proj['id'] ?>'">Supprimer</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($projets)): ?>
        <tr><td colspan="8" style="text-align:center;color:#9a88b8;padding:2rem;font-style:italic">Aucun projet — <a href="/admin/projet-form.php" style="color:#2D1B69">Ajouter le premier</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
