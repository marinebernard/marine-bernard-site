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
    .editor-wrap{border:.5px solid rgba(139,107,177,.3);border-radius:10px;overflow:hidden}
    .editor-toolbar{display:flex;gap:4px;flex-wrap:wrap;padding:8px;background:#F0E6FF;border-bottom:.5px solid rgba(139,107,177,.2)}
    .tb{font-size:11px;padding:5px 9px;border-radius:6px;border:.5px solid rgba(139,107,177,.25);background:#fff;color:#5a4870;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s;white-space:nowrap}
    .tb:hover{background:#8B6BB1;color:#fff;border-color:#8B6BB1}
    .tb-sep{width:1px;background:rgba(139,107,177,.25);margin:2px 4px}
    .editor-wrap textarea.contenu{border:none;border-radius:0;display:block}
    .editor-wrap textarea.contenu:focus{border:none}
    .editor-preview{min-height:400px;padding:1rem 1.2rem;font-family:'Georgia',serif;font-size:14px;line-height:1.8;color:#2D1B69;background:#fff;overflow-y:auto}
    .editor-preview h2{font-family:'Playfair Display',serif;font-size:20px;margin:1.2rem 0 .6rem}
    .editor-preview h3{font-family:'Playfair Display',serif;font-size:17px;margin:1rem 0 .5rem}
    .editor-preview h4{font-size:15px;margin:.8rem 0 .4rem}
    .editor-preview p{margin-bottom:.8rem}
    .editor-preview strong{color:#2D1B69}
    .editor-preview blockquote{border-left:3px solid #C9A96E;padding-left:1rem;margin:.8rem 0;font-style:italic;color:#5a4870}
    .editor-preview hr{border:none;border-top:.5px solid rgba(139,107,177,.25);margin:1.2rem 0}
    .editor-preview a{color:#8B6BB1}
    .editor-preview .puce-or{position:relative;padding-left:1.3rem;margin-bottom:.5rem}
    .editor-preview .puce-or::before{content:'✦';position:absolute;left:0;color:#C9A96E}
    .editor-preview .encart-conseil{background:#EAF3DE;border-radius:10px;padding:.8rem 1rem;margin:.8rem 0;font-size:13px}
    .editor-preview .encart-attention{background:#FAEEDA;border-radius:10px;padding:.8rem 1rem;margin:.8rem 0;font-size:13px}
    .editor-footer{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:6px 12px;background:#FAF8FF;border-top:.5px solid rgba(139,107,177,.15);font-size:11px;color:#9a88b8}
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
            <input type="number" name="temps_lecture" id="temps_lecture" min="1" max="60" value="<?= $article['temps_lecture'] ?? '' ?>" placeholder="5">
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
        <div class="editor-wrap">
          <div class="editor-toolbar">
            <button type="button" class="tb" onclick="fmt('line','## ')">H2</button>
            <button type="button" class="tb" onclick="fmt('line','### ')">H3</button>
            <button type="button" class="tb" onclick="fmt('line','#### ')">H4</button>
            <span class="tb-sep"></span>
            <button type="button" class="tb" onclick="fmt('wrap','**','**')"><strong>G</strong></button>
            <button type="button" class="tb" onclick="fmt('wrap','*','*')"><em>I</em></button>
            <button type="button" class="tb" onclick="fmt('wrap','~~','~~')"><s>B</s></button>
            <span class="tb-sep"></span>
            <button type="button" class="tb" onclick="insertLine('✦ ')">✦ Liste</button>
            <button type="button" class="tb" onclick="insertLine('- ')">– Liste</button>
            <button type="button" class="tb" onclick="insertLine('1. ')">1. Liste</button>
            <button type="button" class="tb" onclick="insertLine('&gt; ')">Citation</button>
            <span class="tb-sep"></span>
            <button type="button" class="tb" onclick="insertBlock('\n:::conseil\n💡 \n:::\n')">💡 Conseil</button>
            <button type="button" class="tb" onclick="insertBlock('\n:::attention\n⚠️ \n:::\n')">⚠️ Attention</button>
            <button type="button" class="tb" onclick="insertBlock('\n---\n')">Séparateur</button>
            <button type="button" class="tb" onclick="insertLink()">🔗 Lien</button>
            <span class="tb-sep"></span>
            <button type="button" class="tb" onclick="calcReadTime()">⏱ Calculer temps de lecture</button>
            <button type="button" class="tb" id="previewToggleBtn" onclick="togglePreview()">👁 Aperçu</button>
            <button type="button" class="tb" onclick="copyAll()">📋 Copier</button>
          </div>
          <textarea name="contenu" id="contenuArea" class="contenu" oninput="updateCounter()" placeholder="Rédige ton article ici...

## Titre de section

Paragraphe de texte...

✦ Point important
✦ Autre point

> Citation ou mise en avant

:::conseil
💡 Un conseil utile
:::"><?= htmlspecialchars($article['contenu'] ?? '') ?></textarea>
          <div class="editor-preview" id="editorPreview" style="display:none"></div>
          <div class="editor-footer">
            <span id="wordCounter">0 mot</span>
            <span>## H2 · ### H3 · **gras** · *italique* · ~~barré~~ · ✦/- listes · &gt; citation · :::conseil:::/:::attention::: · [texte](url)</span>
          </div>
        </div>
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

  function fmt(mode, a, b) {
    const ta = document.getElementById('contenuArea');
    ta.focus();
    if (mode === 'wrap') {
      const start = ta.selectionStart, end = ta.selectionEnd;
      const sel = ta.value.substring(start, end);
      ta.value = ta.value.substring(0, start) + a + sel + b + ta.value.substring(end);
      ta.setSelectionRange(start + a.length, start + a.length + sel.length);
    } else if (mode === 'line') {
      const start = ta.selectionStart;
      const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
      let lineEnd = ta.value.indexOf('\n', start);
      if (lineEnd === -1) lineEnd = ta.value.length;
      const line = ta.value.substring(lineStart, lineEnd).replace(/^#{1,4}\s*/, '');
      const newLine = a + line;
      ta.value = ta.value.substring(0, lineStart) + newLine + ta.value.substring(lineEnd);
      ta.setSelectionRange(lineStart + newLine.length, lineStart + newLine.length);
    }
    updateCounter();
  }

  function insertLine(prefix) {
    const ta = document.getElementById('contenuArea');
    ta.focus();
    const start = ta.selectionStart;
    const needsNL = start > 0 && ta.value[start - 1] !== '\n';
    const insertion = (needsNL ? '\n' : '') + prefix;
    ta.value = ta.value.substring(0, start) + insertion + ta.value.substring(start);
    const pos = start + insertion.length;
    ta.setSelectionRange(pos, pos);
    updateCounter();
  }

  function insertBlock(block) {
    const ta = document.getElementById('contenuArea');
    ta.focus();
    const start = ta.selectionStart;
    ta.value = ta.value.substring(0, start) + block + ta.value.substring(start);
    const pos = start + block.length;
    ta.setSelectionRange(pos, pos);
    updateCounter();
  }

  function insertLink() {
    const url = prompt('URL du lien :');
    if (!url) return;
    const label = prompt('Texte du lien :', url);
    insertBlock('[' + (label || url) + '](' + url + ')');
  }

  function updateCounter() {
    const ta = document.getElementById('contenuArea');
    const words = ta.value.trim() ? ta.value.trim().split(/\s+/).length : 0;
    const mins = Math.max(1, Math.round(words / 200));
    document.getElementById('wordCounter').textContent = words + ' mot' + (words !== 1 ? 's' : '') + ' — environ ' + mins + ' min de lecture';
  }

  function calcReadTime() {
    const ta = document.getElementById('contenuArea');
    const words = ta.value.trim() ? ta.value.trim().split(/\s+/).length : 0;
    const mins = Math.max(1, Math.round(words / 200));
    const champ = document.getElementById('temps_lecture');
    if (champ) champ.value = mins;
    alert('Temps de lecture estimé : ' + mins + ' min (' + words + ' mots à 200 mots/min)');
  }

  function parseMarkdownPreview(md) {
    if (!md || !md.trim()) return '<p style="color:#9a88b8;font-style:italic">Aucun contenu</p>';

    const blocks = [];
    md = md.replace(/:::(conseil|attention)\n([\s\S]*?)\n:::/g, function(m, type, content) {
      const idx = blocks.length;
      blocks.push('<div class="encart-' + type + '">' + content.trim().replace(/\n/g, '<br>') + '</div>');
      return '\n\n@@BLOCK' + idx + '@@\n\n';
    });

    let html = md
      .replace(/^#### (.+)$/gm, '<h4>$1</h4>')
      .replace(/^### (.+)$/gm, '<h3>$1</h3>')
      .replace(/^## (.+)$/gm, '<h2>$1</h2>')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/~~(.+?)~~/g, '<s>$1</s>')
      .replace(/\*(.+?)\*/g, '<em>$1</em>')
      .replace(/^✦ (.+)$/gm, '<div class="puce-or">$1</div>')
      .replace(/^- (.+)$/gm, '<li style="margin-left:1.2rem">$1</li>')
      .replace(/^\d+\. (.+)$/gm, '<li style="margin-left:1.2rem;list-style:decimal">$1</li>')
      .replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>')
      .replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>')
      .replace(/^---$/gm, '<hr>')
      .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

    html = html.split(/\n\n+/).map(function(p) {
      p = p.trim();
      if (!p) return '';
      const blockMatch = p.match(/^@@BLOCK(\d+)@@$/);
      if (blockMatch) return blocks[blockMatch[1]];
      if (p.match(/^<(h[2-4]|div|blockquote|hr|li)/)) return p;
      return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
    }).join('');

    return html;
  }

  function togglePreview() {
    const ta = document.getElementById('contenuArea');
    const preview = document.getElementById('editorPreview');
    const btn = document.getElementById('previewToggleBtn');
    const showing = preview.style.display !== 'none';
    if (showing) {
      preview.style.display = 'none';
      ta.style.display = 'block';
      btn.textContent = '👁 Aperçu';
    } else {
      preview.innerHTML = parseMarkdownPreview(ta.value);
      preview.style.display = 'block';
      ta.style.display = 'none';
      btn.textContent = '✏️ Éditer';
    }
  }

  function copyAll() {
    const ta = document.getElementById('contenuArea');
    ta.select();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(ta.value).then(function() { alert('Contenu copié !'); });
    } else {
      document.execCommand('copy');
      alert('Contenu copié !');
    }
  }

  document.getElementById('contenuArea').addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
      e.preventDefault();
      const start = this.selectionStart, end = this.selectionEnd;
      this.value = this.value.substring(0, start) + '  ' + this.value.substring(end);
      this.selectionStart = this.selectionEnd = start + 2;
    }
  });

  updateCounter();
  </script>
</body>
</html>
