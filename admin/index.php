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
  $stmt = $pdo->prepare("SELECT photo_principale, photos_galerie, fichier_gpx FROM parcours WHERE id = ?");
  $stmt->execute([(int)$_GET['delete']]);
  $p = $stmt->fetch();
  if ($p) {
    if ($p['photo_principale'] && file_exists('../' . $p['photo_principale'])) unlink('../' . $p['photo_principale']);
    $galerie = json_decode($p['photos_galerie'] ?? '[]', true);
    foreach ($galerie as $photo) { if (file_exists('../' . $photo)) unlink('../' . $photo); }
    if ($p['fichier_gpx'] && file_exists('../' . $p['fichier_gpx'])) unlink('../' . $p['fichier_gpx']);
    $pdo->prepare("DELETE FROM parcours WHERE id = ?")->execute([(int)$_GET['delete']]);
  }
  header('Location: /admin/index.php?deleted=1');
  exit;
}

$total = $pdo->query("SELECT COUNT(*) FROM parcours")->fetchColumn();
$coeurs = $pdo->query("SELECT COUNT(*) FROM parcours WHERE coup_de_coeur = 1")->fetchColumn();
$visibles = $pdo->query("SELECT COUNT(*) FROM parcours WHERE visible = 1")->fetchColumn();
$parcours = $pdo->query("SELECT * FROM parcours ORDER BY date_creation DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Parcours</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;color:#2D1B69;min-height:100vh}
    .admin-header{background:#2D1B69;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
    .admin-logo{font-family:'Playfair Display',serif;font-style:italic;font-size:15px;color:#fff}
    .admin-nav{display:flex;gap:12px;align-items:center}
    .admin-nav a{font-size:12px;color:rgba(255,255,255,.7);text-decoration:none;transition:color .2s}
    .admin-nav a:hover{color:#fff}
    .admin-nav .btn-logout{background:rgba(255,255,255,.1);border:.5px solid rgba(255,255,255,.25);border-radius:15px;padding:4px 12px;color:#fff;font-size:12px;text-decoration:none}
    .content{padding:2rem}
    .stats{display:flex;gap:12px;margin-bottom:1.5rem}
    .stat-card{background:#fff;border:.5px solid rgba(139,107,177,.18);border-radius:12px;padding:1rem 1.2rem;flex:1}
    .stat-num{font-family:'Playfair Display',serif;font-size:28px;color:#2D1B69;line-height:1}
    .stat-label{font-size:11px;color:#9a88b8;letter-spacing:.08em;text-transform:uppercase;margin-top:3px}
    .actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
    .btn-add{display:inline-flex;align-items:center;gap:6px;background:#C9A96E;color:#2D1B69;border:none;border-radius:22px;padding:9px 20px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;transition:background .2s}
    .btn-add:hover{background:#b8924a}
    .page-title{font-family:'Playfair Display',serif;font-size:20px;color:#2D1B69}
    .success{background:#EAF3DE;color:#27500A;border-radius:8px;padding:.6rem 1rem;font-size:13px;margin-bottom:1rem}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;border:.5px solid rgba(139,107,177,.15)}
    th{background:#F0E6FF;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8B6BB1;padding:.7rem 1rem;text-align:left;font-weight:500}
    td{padding:.8rem 1rem;font-size:13px;border-top:.5px solid rgba(139,107,177,.1);vertical-align:middle}
    tr:hover td{background:#FAF8FF}
    .badge{display:inline-block;font-size:10px;padding:2px 8px;border-radius:8px;font-weight:500}
    .badge-facile{background:#EAF3DE;color:#27500A}
    .badge-modere{background:#FAEEDA;color:#633806}
    .badge-difficile{background:#EEEDFE;color:#3C3489}
    .badge-hidden{background:#f3f4f6;color:#9ca3af}
    .coeur{color:#C9A96E;font-size:14px}
    .td-actions{display:flex;gap:6px}
    .btn-edit{font-size:11px;padding:4px 10px;border-radius:8px;background:#F0E6FF;color:#2D1B69;border:none;cursor:pointer;text-decoration:none;transition:background .2s}
    .btn-edit:hover{background:#d4b8f0}
    .btn-del{font-size:11px;padding:4px 10px;border-radius:8px;background:#fef2f2;color:#dc2626;border:none;cursor:pointer;transition:background .2s}
    .btn-del:hover{background:#fee2e2}
    .btn-card{font-size:11px;padding:4px 10px;border-radius:8px;background:#F0E6FF;color:#2D1B69;border:none;cursor:pointer;transition:background .2s}
    .btn-card:hover{background:#d4b8f0}
    .btn-card.loading{opacity:.6;cursor:wait}
    .photo-thumb{width:40px;height:40px;object-fit:cover;border-radius:6px}
    .no-photo{width:40px;height:40px;background:#F0E6FF;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
  </style>
</head>
<body>
  <header class="admin-header">
    <span class="admin-logo">Marine Bernard ✿</span>
    <nav class="admin-nav">
      <a href="/" target="_blank">Voir le site ↗</a>
      <a href="/admin/parcours-form.php" class="btn-add">+ Ajouter un parcours</a>
      <a href="/admin/login.php?logout=1" class="btn-logout">Déconnexion</a>
    </nav>
  </header>
  <div class="content">
    <?php if (isset($_GET['deleted'])): ?>
      <div class="success">✓ Parcours supprimé avec succès.</div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
      <div class="success">✓ Parcours enregistré avec succès.</div>
    <?php endif; ?>
    <div class="stats">
      <div class="stat-card"><div class="stat-num"><?= $total ?></div><div class="stat-label">Parcours total</div></div>
      <div class="stat-card"><div class="stat-num"><?= $coeurs ?></div><div class="stat-label">Coups de cœur</div></div>
      <div class="stat-card"><div class="stat-num"><?= $visibles ?></div><div class="stat-label">Visibles sur le site</div></div>
    </div>
    <div style="background:#F0E6FF;border-radius:10px;padding:.7rem 1rem;font-size:12px;color:#5a4870;margin-bottom:1rem;display:flex;align-items:center;gap:8px">
      <span>💡</span>
      <span>Première utilisation ? <a href="/admin/fonts/download-fonts.php" style="color:#2D1B69;font-weight:500">Télécharge les polices →</a> pour des cartes avec les bonnes typographies.</span>
    </div>
    <div class="actions">
      <h1 class="page-title">Mes parcours</h1>
      <a href="/admin/parcours-form.php" class="btn-add">➕ Ajouter un parcours</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>Photo</th>
          <th>Titre</th>
          <th>Région</th>
          <th>Distance</th>
          <th>Difficulté</th>
          <th>♥</th>
          <th>Visible</th>
          <th>Date rando</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($parcours as $p): ?>
        <tr>
          <td>
            <?php if ($p['photo_principale']): ?>
              <img src="/<?= htmlspecialchars($p['photo_principale']) ?>" class="photo-thumb" alt="<?= htmlspecialchars($p['titre']) ?>">
            <?php else: ?>
              <div class="no-photo">🌿</div>
            <?php endif; ?>
          </td>
          <td><strong><?= htmlspecialchars($p['titre']) ?></strong></td>
          <td><?= htmlspecialchars($p['region']) ?></td>
          <td><?= $p['distance'] ? $p['distance'] . ' km' : '—' ?></td>
          <td><span class="badge badge-<?= $p['difficulte'] ?>"><?= ucfirst($p['difficulte']) ?></span></td>
          <td><?= $p['coup_de_coeur'] ? '<span class="coeur">✦</span>' : '' ?></td>
          <td><?= $p['visible'] ? '✓' : '<span class="badge badge-hidden">Masqué</span>' ?></td>
          <td><?= $p['date_rando'] ? date('d/m/Y', strtotime($p['date_rando'])) : '—' ?></td>
          <td>
            <div class="td-actions">
              <a href="/admin/parcours-form.php?id=<?= $p['id'] ?>" class="btn-edit">Modifier</a>
              <button class="btn-card" onclick="genererCarte(<?= $p['id'] ?>, this)" title="Générer carte Facebook/LinkedIn">🖼 Carte</button>
              <button class="btn-del" onclick="if(confirm('Supprimer ce parcours ?')) window.location='/admin/index.php?delete=<?= $p['id'] ?>'">Supprimer</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($parcours)): ?>
        <tr><td colspan="9" style="text-align:center;color:#9a88b8;padding:2rem;font-style:italic">Aucun parcours encore — <a href="/admin/parcours-form.php">Ajouter le premier</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <script>
    async function genererCarte(id, btn) {
      btn.classList.add('loading');
      btn.textContent = '⏳ Génération...';
      try {
        const res = await fetch('/admin/generate-card.php?id=' + id);
        const data = await res.json();
        if (data.success) {
          const a = document.createElement('a');
          a.href = data.url;
          a.download = data.filename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          btn.textContent = '✓ Téléchargée';
          btn.style.background = '#EAF3DE';
          btn.style.color = '#27500A';
          setTimeout(() => {
            btn.textContent = '🖼 Carte';
            btn.style.background = '';
            btn.style.color = '';
            btn.classList.remove('loading');
          }, 3000);
        } else {
          alert('Erreur : ' + (data.error || 'Génération impossible'));
          btn.textContent = '🖼 Carte';
          btn.classList.remove('loading');
        }
      } catch(e) {
        alert('Erreur de connexion');
        btn.textContent = '🖼 Carte';
        btn.classList.remove('loading');
      }
    }
  </script>
</body>
</html>
