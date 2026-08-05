<?php
require_once __DIR__ . '/../api/config.php';

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) { header('Location: /pages/sentiers.html'); exit; }

$stmt = $pdo->prepare("SELECT * FROM parcours WHERE id = ? AND visible = 1");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { header('Location: /pages/sentiers.html'); exit; }

$photos = json_decode($p['photos_galerie'] ?? '[]', true) ?: [];
$tags   = json_decode($p['tags'] ?? '[]', true) ?: [];

$diffMap = [
  'facile'    => ['label' => 'Facile',    'cls' => 'diff-facile',    'color' => '#27500A'],
  'modere'    => ['label' => 'Modéré',    'cls' => 'diff-modere',    'color' => '#633806'],
  'difficile' => ['label' => 'Difficile', 'cls' => 'diff-difficile', 'color' => '#3C3489'],
];
$diff = $diffMap[$p['difficulte']] ?? $diffMap['facile'];

$balisageLabels = [
  'jaune'       => ['label' => 'Jaune — Sentier PR',     'color' => '#F5C842'],
  'rouge'       => ['label' => 'Rouge — GRP',            'color' => '#DC3C3C'],
  'rouge_blanc' => ['label' => 'Rouge/Blanc — GR',       'color' => '#DC3C3C'],
  'orange'      => ['label' => 'Orange — Sentier local', 'color' => '#E68A28'],
  'bleu'        => ['label' => 'Bleu — ONF',             'color' => '#3C78DC'],
  'vert'        => ['label' => 'Vert — Véloroute',       'color' => '#3BB43C'],
  'mixte'       => ['label' => 'Balisage mixte',         'color' => '#8B6BB1'],
  'autre'       => ['label' => 'Balisage local',         'color' => '#888'],
];
$balise = $balisageLabels[$p['type_balisage'] ?? ''] ?? null;

$mapsUrl = $p['lieu_lat'] && $p['lieu_lng']
  ? "https://www.google.com/maps?q={$p['lieu_lat']},{$p['lieu_lng']}"
  : ($p['lieu_depart'] ? "https://www.google.com/maps/search/" . urlencode($p['lieu_depart']) : null);

$osmEmbed = $p['lieu_lat'] && $p['lieu_lng']
  ? "https://www.openstreetmap.org/export/embed.html?bbox=" .
    ($p['lieu_lng']-0.02).",".($p['lieu_lat']-0.015).",".
    ($p['lieu_lng']+0.02).",".($p['lieu_lat']+0.015).
    "&layer=mapnik&marker={$p['lieu_lat']},{$p['lieu_lng']}"
  : null;

$allPhotos = [];
if ($p['photo_principale']) $allPhotos[] = $p['photo_principale'];
$allPhotos = array_merge($allPhotos, $photos);

$pageTitle  = htmlspecialchars($p['titre']) . ' | La ch\'tite randonneuse';
$pageDesc   = htmlspecialchars($p['description'] ?? 'Randonnée ' . ($p['region'] ?? 'Hauts-de-France') . ' — ' . ($p['distance'] ?? '') . ' km par @lachtiterandonneuse');
$pageImage  = $p['photo_principale'] ? 'https://marine-bernard.fr/' . $p['photo_principale'] : 'https://marine-bernard.fr/images/shared/marine-bernard.png';
$pageUrl    = 'https://marine-bernard.fr/pages/sentier.php?id=' . $p['id'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <meta name="description" content="<?= $pageDesc ?>">
  <meta property="og:title" content="<?= htmlspecialchars($p['titre']) ?>">
  <meta property="og:description" content="<?= $pageDesc ?>">
  <meta property="og:image" content="<?= $pageImage ?>">
  <meta property="og:url" content="<?= $pageUrl ?>">
  <meta property="og:type" content="article">
  <link rel="canonical" href="<?= $pageUrl ?>">
  <link rel="icon" type="image/svg+xml" href="/images/shared/favicon.svg">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="/css/global.css">
  <link rel="stylesheet" href="/css/rando.css">
  <style>
    .sentier-hero{position:relative;height:460px;overflow:hidden;background:#27500A}
    .sentier-hero-img{width:100%;height:100%;object-fit:cover;display:block;filter:brightness(.75)}
    .sentier-hero-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(10,6,30,.85) 0%,rgba(10,6,30,.3) 50%,transparent 100%)}
    .sentier-hero-content{position:absolute;bottom:0;left:0;right:0;padding:2rem 2.5rem}
    .sentier-hero-region{font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:#C9A96E;font-weight:600;margin-bottom:.4rem;display:flex;align-items:center;gap:6px}
    .sentier-hero-title{font-family:'Playfair Display',serif;font-size:34px;color:#fff;line-height:1.15;margin-bottom:.8rem;font-style:italic}
    .sentier-hero-badges{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .sentier-hero-badge{font-size:11px;font-weight:500;padding:4px 12px;border-radius:10px}

    .sentier-back{position:absolute;top:1.2rem;left:2rem;display:inline-flex;align-items:center;gap:6px;font-size:13px;color:rgba(255,255,255,.8);text-decoration:none;background:rgba(10,6,30,.4);padding:6px 14px;border-radius:20px;backdrop-filter:blur(8px);transition:all .2s;border:.5px solid rgba(255,255,255,.15)}
    .sentier-back:hover{background:rgba(10,6,30,.7);color:#fff}

    .sentier-layout{display:grid;grid-template-columns:1fr 340px;gap:2rem;padding:2rem 2.5rem;max-width:1100px;margin:0 auto}

    .sentier-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:1.5rem}
    .sentier-stat{background:#fff;border:.5px solid rgba(59,109,17,.15);border-radius:12px;padding:.9rem 1rem;text-align:center}
    .sentier-stat-val{font-family:'Playfair Display',serif;font-size:20px;color:#27500A;display:block;line-height:1;margin-bottom:4px}
    .sentier-stat-label{font-size:10px;color:#7a8f5a;letter-spacing:.1em;text-transform:uppercase}

    .sentier-section{margin-bottom:1.5rem}
    .sentier-section-title{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#C9A96E;margin-bottom:.7rem;display:flex;align-items:center;gap:8px}
    .sentier-section-title::after{content:'';flex:1;height:.5px;background:rgba(59,109,17,.12)}

    .sentier-description{font-size:15px;color:#3d3d3d;line-height:1.85;background:#fff;border-radius:14px;padding:1.2rem 1.4rem;border:.5px solid rgba(59,109,17,.1)}

    .sentier-tags{display:flex;flex-wrap:wrap;gap:6px}
    .sentier-tag{font-size:12px;padding:4px 12px;border-radius:20px;background:#F0E6FF;color:#2D1B69;border:.5px solid rgba(139,107,177,.2)}

    .sentier-gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .sentier-gallery-item{border-radius:10px;overflow:hidden;cursor:none;aspect-ratio:4/3}
    .sentier-gallery-item img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
    .sentier-gallery-item:hover img{transform:scale(1.06)}

    .sentier-sidebar-card{background:#fff;border:.5px solid rgba(59,109,17,.15);border-radius:16px;padding:1.3rem;margin-bottom:1rem}
    .sentier-sidebar-card-title{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#3B6D11;margin-bottom:.9rem;display:flex;align-items:center;gap:6px}

    .sentier-info-list{display:flex;flex-direction:column;gap:.6rem}
    .sentier-info-item{display:flex;align-items:flex-start;gap:10px;font-size:13px}
    .sentier-info-item>span:first-child{font-size:15px;flex-shrink:0;margin-top:1px}
    .sentier-info-label{font-size:10px;color:#9a88b8;text-transform:uppercase;letter-spacing:.08em;display:block;margin-bottom:1px}
    .sentier-info-val{color:#2D1B69;font-weight:500}

    .sentier-balise-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0;margin-top:2px;border:2px solid rgba(255,255,255,.8);box-shadow:0 0 0 1px rgba(0,0,0,.1)}

    .sentier-map{border-radius:12px;overflow:hidden;border:.5px solid rgba(59,109,17,.15);height:220px}
    .sentier-map iframe{width:100%;height:100%;border:none;display:block}

    .btn-gpx-big{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#27500A;color:#fff;font-size:14px;font-weight:600;padding:13px 20px;border-radius:14px;border:none;cursor:none;font-family:'Inter',sans-serif;transition:background .2s;text-decoration:none;margin-bottom:8px}
    .btn-gpx-big:hover{background:#3B6D11}

    .btn-maps-big{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#EAF3DE;color:#27500A;font-size:13px;font-weight:500;padding:10px 16px;border-radius:14px;border:.5px solid rgba(59,109,17,.2);cursor:none;font-family:'Inter',sans-serif;transition:all .2s;text-decoration:none;margin-bottom:8px}
    .btn-maps-big:hover{background:#C0DD97}

    .btn-komoot-big{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#fff;color:#6AA127;font-size:13px;font-weight:500;padding:10px 16px;border-radius:14px;border:.5px solid rgba(106,161,39,.25);cursor:none;font-family:'Inter',sans-serif;transition:all .2s;text-decoration:none}
    .btn-komoot-big:hover{background:#EAF3DE}

    .sentier-share-label{font-size:11px;color:#9a88b8;text-align:center;margin-bottom:.5rem;letter-spacing:.06em}
    .sentier-share-btns{display:flex;gap:6px}
    .sentier-share-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;font-size:12px;font-weight:500;padding:8px 10px;border-radius:10px;text-decoration:none;transition:opacity .2s;border:none;cursor:none;font-family:'Inter',sans-serif}
    .sentier-share-btn:hover{opacity:.85}
    .share-fb{background:#1877F2;color:#fff}
    .share-wa{background:#25D366;color:#fff}
    .share-copy{background:#F0E6FF;color:#2D1B69}

    .lightbox-overlay{position:fixed;inset:0;background:rgba(10,6,30,.95);z-index:9000;display:none;align-items:center;justify-content:center;flex-direction:column;gap:12px;padding:1rem;backdrop-filter:blur(8px)}
    .lightbox-overlay.active{display:flex}
    .lightbox-img{max-width:90vw;max-height:75vh;border-radius:10px;object-fit:contain}
    .lightbox-close{position:absolute;top:1rem;right:1rem;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.1);border:.5px solid rgba(255,255,255,.2);color:#fff;font-size:16px;cursor:none;display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif}
    .lightbox-nav{display:flex;gap:8px;align-items:center}
    .lightbox-btn{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.1);border:.5px solid rgba(255,255,255,.2);color:#fff;font-size:22px;cursor:none;display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;transition:background .2s;line-height:1}
    .lightbox-btn:hover{background:rgba(255,255,255,.22)}
    .lightbox-counter{font-size:12px;color:rgba(255,255,255,.5);letter-spacing:.08em}
    .lightbox-thumbs{display:flex;gap:6px;flex-wrap:wrap;justify-content:center;max-width:600px}
    .lightbox-thumb{width:54px;height:54px;border-radius:7px;object-fit:cover;cursor:none;opacity:.5;border:2px solid transparent;transition:all .2s}
    .lightbox-thumb.active{opacity:1;border-color:#C0DD97}

    .copy-toast{position:fixed;bottom:2rem;left:50%;transform:translateX(-50%) translateY(10px);background:#27500A;color:#fff;padding:.6rem 1.4rem;border-radius:22px;font-size:13px;opacity:0;transition:all .3s;pointer-events:none;white-space:nowrap;z-index:9999}
    .copy-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}

    @media(max-width:900px){
      .sentier-layout{grid-template-columns:1fr;padding:1.5rem}
      .sentier-sidebar{order:-1}
      .sentier-hero{height:320px}
      .sentier-hero-title{font-size:24px}
      .sentier-stats{grid-template-columns:repeat(2,1fr)}
      .sentier-gallery{grid-template-columns:repeat(2,1fr)}
      .sentier-share-btns{flex-direction:column}
    }
  </style>
</head>
<body class="rando">
  <div id="header"></div>

  <!-- CURSEUR FLEUR -->
  <div id="cursor-flower">
    <svg viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
      <circle cx="15" cy="15" r="4.5" fill="#2D1B69"/>
      <ellipse cx="15" cy="6.5" rx="3.8" ry="5.5" fill="#8B6BB1" opacity="0.9"/>
      <ellipse cx="15" cy="23.5" rx="3.8" ry="5.5" fill="#8B6BB1" opacity="0.9"/>
      <ellipse cx="6.5" cy="15" rx="5.5" ry="3.8" fill="#C9A96E" opacity="0.85"/>
      <ellipse cx="23.5" cy="15" rx="5.5" ry="3.8" fill="#C9A96E" opacity="0.85"/>
      <ellipse cx="9" cy="9" rx="3.2" ry="4.8" fill="#d4b8f0" opacity="0.8" transform="rotate(-45 9 9)"/>
      <ellipse cx="21" cy="9" rx="3.2" ry="4.8" fill="#d4b8f0" opacity="0.8" transform="rotate(45 21 9)"/>
      <ellipse cx="9" cy="21" rx="3.2" ry="4.8" fill="#d4b8f0" opacity="0.8" transform="rotate(45 9 21)"/>
      <ellipse cx="21" cy="21" rx="3.2" ry="4.8" fill="#d4b8f0" opacity="0.8" transform="rotate(-45 21 21)"/>
      <circle cx="15" cy="15" r="3.2" fill="#FAF8FF"/>
    </svg>
  </div>

  <div class="sentier-hero">
    <?php if ($p['photo_principale']): ?>
    <img class="sentier-hero-img" src="/<?= htmlspecialchars($p['photo_principale']) ?>" alt="<?= htmlspecialchars($p['titre']) ?>">
    <?php else: ?>
    <div style="width:100%;height:100%;background:linear-gradient(135deg,#27500A,#3B6D11)"></div>
    <?php endif; ?>
    <div class="sentier-hero-overlay"></div>
    <a href="/pages/sentiers.html" class="sentier-back">← Mes sentiers</a>
    <div class="sentier-hero-content">
      <p class="sentier-hero-region">
        📍 <?= htmlspecialchars($p['region'] ?? '') ?><?= $p['lieu_ville'] ? ' · ' . htmlspecialchars($p['lieu_ville']) : '' ?>
      </p>
      <h1 class="sentier-hero-title"><?= htmlspecialchars($p['titre']) ?></h1>
      <div class="sentier-hero-badges">
        <span class="sentier-hero-badge <?= $diff['cls'] ?>"><?= $diff['label'] ?></span>
        <?php if ($p['date_rando']): ?>
        <span class="sentier-hero-badge" style="background:rgba(255,255,255,.15);color:rgba(255,255,255,.8)">
          📅 <?= date('d/m/Y', strtotime($p['date_rando'])) ?>
        </span>
        <?php endif; ?>
        <?php if ($p['fichier_gpx']): ?>
        <span class="sentier-hero-badge" style="background:rgba(192,221,151,.2);color:#C0DD97;border:.5px solid rgba(192,221,151,.3)">
          ⬇ GPX disponible
        </span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="sentier-layout">

    <div class="sentier-main">

      <div class="sentier-stats">
        <?php if ($p['distance']): ?>
        <div class="sentier-stat">
          <span class="sentier-stat-val"><?= $p['distance'] ?> km</span>
          <span class="sentier-stat-label">Distance</span>
        </div>
        <?php endif; ?>
        <?php if ($p['duree']): ?>
        <div class="sentier-stat">
          <span class="sentier-stat-val"><?= htmlspecialchars($p['duree']) ?></span>
          <span class="sentier-stat-label">Durée</span>
        </div>
        <?php endif; ?>
        <?php if ($p['denivele']): ?>
        <div class="sentier-stat">
          <span class="sentier-stat-val">+<?= $p['denivele'] ?>m</span>
          <span class="sentier-stat-label">Dénivelé</span>
        </div>
        <?php endif; ?>
        <div class="sentier-stat">
          <span class="sentier-stat-val" style="color:<?= $diff['color'] ?>"><?= $diff['label'] ?></span>
          <span class="sentier-stat-label">Difficulté</span>
        </div>
      </div>

      <?php if ($p['description']): ?>
      <div class="sentier-section">
        <p class="sentier-section-title">📝 Description</p>
        <div class="sentier-description"><?= nl2br(htmlspecialchars($p['description'])) ?></div>
      </div>
      <?php endif; ?>

      <?php if (!empty($allPhotos)): ?>
      <div class="sentier-section">
        <p class="sentier-section-title">📷 Photos (<?= count($allPhotos) ?>)</p>
        <div class="sentier-gallery" id="gallery">
          <?php foreach ($allPhotos as $i => $photo): ?>
          <div class="sentier-gallery-item" onclick="openLightbox(<?= $i ?>)">
            <img src="/<?= htmlspecialchars($photo) ?>" alt="Photo <?= $i+1 ?>" loading="lazy">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($tags)): ?>
      <div class="sentier-section">
        <p class="sentier-section-title">🏷️ Tags</p>
        <div class="sentier-tags">
          <?php foreach ($tags as $tag): ?>
          <span class="sentier-tag"><?= htmlspecialchars($tag) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <div class="sentier-sidebar">

      <?php if ($p['fichier_gpx'] || $mapsUrl || $p['lien_komoot']): ?>
      <div class="sentier-sidebar-card">
        <p class="sentier-sidebar-card-title">🧭 Naviguer</p>
        <?php if ($p['fichier_gpx']): ?>
        <button class="btn-gpx-big" onclick="telechargerGPX('/<?= htmlspecialchars($p['fichier_gpx']) ?>','<?= htmlspecialchars(addslashes($p['titre'])) ?>')">
          ⬇ Télécharger le GPX
        </button>
        <?php endif; ?>
        <?php if ($mapsUrl): ?>
        <a href="<?= $mapsUrl ?>" target="_blank" rel="noopener" class="btn-maps-big">
          📍 Voir le point de départ
        </a>
        <?php endif; ?>
        <?php if ($p['lien_komoot']): ?>
        <a href="<?= htmlspecialchars($p['lien_komoot']) ?>" target="_blank" rel="noopener" class="btn-komoot-big">
          Voir sur Komoot ↗
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="sentier-sidebar-card">
        <p class="sentier-sidebar-card-title">ℹ️ Informations</p>
        <div class="sentier-info-list">
          <?php if ($p['lieu_depart']): ?>
          <div class="sentier-info-item">
            <span>📍</span>
            <div>
              <span class="sentier-info-label">Point de départ</span>
              <span class="sentier-info-val"><?= htmlspecialchars($p['lieu_depart']) ?></span>
              <?php if ($p['lieu_ville']): ?>
              <span style="font-size:11px;color:#9a88b8;display:block"><?= htmlspecialchars($p['lieu_ville']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($p['type_paysage']): ?>
          <div class="sentier-info-item">
            <span>🌳</span>
            <div>
              <span class="sentier-info-label">Paysage</span>
              <span class="sentier-info-val"><?= ucfirst(htmlspecialchars($p['type_paysage'])) ?></span>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($p['balisage'] && $balise): ?>
          <div class="sentier-info-item">
            <span>🚩</span>
            <div>
              <span class="sentier-info-label">Balisage</span>
              <span class="sentier-info-val" style="display:flex;align-items:center;gap:6px">
                <span class="sentier-balise-dot" style="background:<?= $balise['color'] ?>"></span>
                <?= htmlspecialchars($balise['label']) ?>
              </span>
              <a href="/pages/blog-balisage.html" style="font-size:11px;color:#8B6BB1;text-decoration:none;margin-top:2px;display:block">Comprendre les balisages →</a>
            </div>
          </div>
          <?php elseif (!$p['balisage']): ?>
          <div class="sentier-info-item">
            <span>⚠</span>
            <div>
              <span class="sentier-info-label">Balisage</span>
              <span class="sentier-info-val" style="color:#9a88b8">Non balisé</span>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($p['date_rando']): ?>
          <div class="sentier-info-item">
            <span>📅</span>
            <div>
              <span class="sentier-info-label">Date de la randonnée</span>
              <span class="sentier-info-val"><?= date('d/m/Y', strtotime($p['date_rando'])) ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($osmEmbed): ?>
      <div class="sentier-sidebar-card" style="padding:.8rem">
        <div class="sentier-map">
          <iframe src="<?= $osmEmbed ?>" loading="lazy" title="Carte du sentier" allowfullscreen></iframe>
        </div>
        <?php if ($mapsUrl): ?>
        <a href="<?= $mapsUrl ?>" target="_blank" rel="noopener" style="display:block;text-align:center;font-size:12px;color:#27500A;margin-top:.6rem;text-decoration:none">
          Ouvrir dans Google Maps ↗
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="sentier-sidebar-card">
        <p class="sentier-sidebar-card-title">📤 Partager</p>
        <div class="sentier-share-btns">
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageUrl) ?>"
             target="_blank" rel="noopener" class="sentier-share-btn share-fb">
            📘 Facebook
          </a>
          <a href="https://wa.me/?text=<?= urlencode('🥾 ' . $p['titre'] . ' — ' . $pageUrl) ?>"
             target="_blank" rel="noopener" class="sentier-share-btn share-wa">
            💬 WhatsApp
          </a>
          <button onclick="copyUrl()" class="sentier-share-btn share-copy">
            📋 Copier
          </button>
        </div>
      </div>

    </div>
  </div>

  <div id="footer"></div>

  <div class="lightbox-overlay" id="lbOverlay">
    <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    <img class="lightbox-img" id="lbImg" src="" alt="">
    <div class="lightbox-nav">
      <button class="lightbox-btn" id="lbPrev" onclick="lbGo(-1)">‹</button>
      <span class="lightbox-counter" id="lbCounter"></span>
      <button class="lightbox-btn" id="lbNext" onclick="lbGo(1)">›</button>
    </div>
    <div class="lightbox-thumbs" id="lbThumbs"></div>
  </div>

  <div class="copy-toast" id="copyToast">✓ Lien copié !</div>

  <script src="/js/include.js"></script>
  <script>
  /* ── CURSEUR FLEUR ── */
  const cur=document.getElementById('cursor-flower');
  let mx=0,my=0,cx=0,cy=0;
  document.addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY});
  (function loop(){cx+=(mx-cx)*0.13;cy+=(my-cy)*0.13;cur.style.left=cx+'px';cur.style.top=cy+'px';requestAnimationFrame(loop)})();

  const photos = <?= json_encode($allPhotos) ?>;
  let lbCurrent = 0;

  function openLightbox(i) {
    lbCurrent = i;
    const overlay = document.getElementById('lbOverlay');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    buildThumbs();
    showPhoto(i);
  }

  function closeLightbox() {
    document.getElementById('lbOverlay').classList.remove('active');
    document.body.style.overflow = '';
  }

  function showPhoto(i) {
    lbCurrent = i;
    document.getElementById('lbImg').src = '/' + photos[i];
    document.getElementById('lbCounter').textContent = (i+1) + ' / ' + photos.length;
    document.getElementById('lbPrev').disabled = i === 0;
    document.getElementById('lbNext').disabled = i === photos.length - 1;
    document.querySelectorAll('.lightbox-thumb').forEach((t,j) => t.classList.toggle('active', j===i));
  }

  function lbGo(dir) {
    const next = lbCurrent + dir;
    if (next >= 0 && next < photos.length) showPhoto(next);
  }

  function buildThumbs() {
    const tc = document.getElementById('lbThumbs');
    tc.innerHTML = '';
    if (photos.length <= 1) return;
    photos.forEach((src, i) => {
      const img = document.createElement('img');
      img.className = 'lightbox-thumb' + (i===0?' active':'');
      img.src = '/' + src;
      img.loading = 'lazy';
      img.onclick = () => showPhoto(i);
      tc.appendChild(img);
    });
  }

  document.getElementById('lbOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('lbOverlay')) closeLightbox();
  });

  document.addEventListener('keydown', e => {
    if (!document.getElementById('lbOverlay').classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lbGo(-1);
    if (e.key === 'ArrowRight') lbGo(1);
  });

  async function telechargerGPX(url, titre) {
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error();
      const blob = await res.blob();
      const nomFichier = titre.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g,'')
        .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'') + '.gpx';
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = nomFichier;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    } catch(e) {
      alert('Impossible de télécharger ce GPX.');
    }
  }

  function copyUrl() {
    navigator.clipboard.writeText(window.location.href).then(() => {
      const t = document.getElementById('copyToast');
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 2500);
    });
  }
  </script>
</body>
</html>
