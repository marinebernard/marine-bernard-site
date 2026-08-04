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
  $stmt = $pdo->prepare("SELECT photo_principale FROM articles WHERE id = ?");
  $stmt->execute([(int)$_GET['delete']]);
  $a = $stmt->fetch();
  if ($a && $a['photo_principale'] && file_exists(__DIR__ . '/../' . $a['photo_principale'])) {
    @unlink(__DIR__ . '/../' . $a['photo_principale']);
  }
  $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([(int)$_GET['delete']]);
  header('Location: /admin/articles.php?deleted=1');
  exit;
}

$articles = $pdo->query("SELECT * FROM articles ORDER BY date_creation DESC")->fetchAll();
$total = count($articles);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Articles — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;color:#2D1B69;min-height:100vh}
    .admin-header{background:#2D1B69;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
    .admin-logo{font-family:'Playfair Display',serif;font-style:italic;font-size:15px;color:#fff}
    .admin-nav{display:flex;align-items:center;gap:12px}
    .admin-nav a{font-size:12px;color:rgba(255,255,255,.7);text-decoration:none;transition:color .2s}
    .admin-nav a:hover{color:#fff}
    .btn-add{display:inline-flex;align-items:center;gap:5px;background:#C9A96E;color:#2D1B69;border:none;border-radius:20px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;font-family:'Inter',sans-serif}
    .content{padding:2rem}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem}
    .page-title{font-family:'Playfair Display',serif;font-size:20px;color:#2D1B69}
    .success{background:#EAF3DE;color:#27500A;border-radius:8px;padding:.6rem 1rem;font-size:13px;margin-bottom:1rem}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;border:.5px solid rgba(139,107,177,.15)}
    th{background:#F0E6FF;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8B6BB1;padding:.7rem 1rem;text-align:left;font-weight:500}
    td{padding:.8rem 1rem;font-size:13px;border-top:.5px solid rgba(139,107,177,.08);vertical-align:middle}
    tr:hover td{background:#FAF8FF}
    .badge-cat{font-size:10px;padding:2px 8px;border-radius:8px;font-weight:500;background:#EAF3DE;color:#27500A}
    .badge-hidden{background:#f3f4f6;color:#9ca3af}
    .photo-thumb{width:50px;height:38px;object-fit:cover;border-radius:6px}
    .no-photo{width:50px;height:38px;background:#F0E6FF;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:1rem}
    .td-actions{display:flex;gap:5px}
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
      <a href="/admin/articles.php" style="color:#fff;font-weight:500">Articles</a>
      <a href="/" target="_blank">Voir le site ↗</a>
      <a href="/admin/login.php?logout=1" style="background:rgba(255,255,255,.1);border:.5px solid rgba(255,255,255,.25);border-radius:15px;padding:4px 12px;color:#fff">Déconnexion</a>
    </nav>
  </header>
  <div class="content">
    <?php if (isset($_GET['deleted'])): ?><div class="success">✓ Article supprimé.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="success">✓ Article enregistré.</div><?php endif; ?>
    <div class="page-header">
      <h1 class="page-title">Articles du blog <span style="font-size:14px;color:#9a88b8;font-family:'Inter',sans-serif">(<?= $total ?>)</span></h1>
      <a href="/admin/article-form.php" class="btn-add">✏️ Nouvel article</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>Photo</th>
          <th>Titre</th>
          <th>Catégorie</th>
          <th>Extrait</th>
          <th>Visible</th>
          <th>Publication</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($articles as $a): ?>
        <tr>
          <td>
            <?php if ($a['photo_principale']): ?>
              <img src="/<?= htmlspecialchars($a['photo_principale']) ?>" class="photo-thumb" alt="">
            <?php else: ?>
              <div class="no-photo">✍️</div>
            <?php endif; ?>
          </td>
          <td><strong><?= htmlspecialchars($a['titre']) ?></strong><br><span style="font-size:11px;color:#9a88b8">/pages/blog-<?= htmlspecialchars($a['slug']) ?>.html</span></td>
          <td><span class="badge-cat"><?= htmlspecialchars($a['categorie']) ?></span></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#7a6090"><?= htmlspecialchars($a['extrait'] ?? '') ?></td>
          <td><?= $a['visible'] ? '✓' : '<span class="badge-cat badge-hidden">Masqué</span>' ?></td>
          <td style="font-size:12px;color:#7a6090"><?= $a['date_publication'] ? date('d/m/Y', strtotime($a['date_publication'])) : '—' ?></td>
          <td>
            <div class="td-actions">
              <a href="/admin/article-form.php?id=<?= $a['id'] ?>" class="btn-edit">Modifier</a>
              <a href="/pages/blog-<?= htmlspecialchars($a['slug']) ?>.html" target="_blank" class="btn-view">Voir</a>
              <button class="btn-del" onclick="if(confirm('Supprimer cet article ?')) window.location='/admin/articles.php?delete=<?= $a['id'] ?>'">Supprimer</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($articles)): ?>
        <tr><td colspan="7" style="text-align:center;color:#9a88b8;padding:2rem;font-style:italic">Aucun article — <a href="/admin/article-form.php" style="color:#2D1B69">Écrire le premier</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
