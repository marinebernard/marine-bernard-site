<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

function downloadFont($url, $path) {
  if (file_exists($path)) return 'exists';
  if (!function_exists('curl_init')) return 'no_curl';
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; FontDownloader/1.0)',
  ]);
  $content = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);
  if ($content === false || $httpCode !== 200) return 'error_' . $httpCode . '_' . $error;
  if (strlen($content) < 1000) return 'too_small';
  $sig = substr($content, 0, 4);
  $validSfntSignatures = ["\x00\x01\x00\x00", 'OTTO', 'true', 'typ1', 'ttcf'];
  if (!in_array($sig, $validSfntSignatures, true)) return 'not_a_ttf';
  if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
  file_put_contents($path, $content);
  return 'ok';
}

$dir = __DIR__ . '/';

$fonts = [
  [
    'name'  => 'Inter-Regular.ttf',
    'label' => 'Inter Regular',
    'urls'  => [
      'https://github.com/google/fonts/raw/main/ofl/inter/Inter%5Bopsz%2Cwght%5D.ttf',
      'https://fonts.gstatic.com/s/inter/v13/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuLyfAZ9hiJ-Ek-_EeA.woff2',
      'https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.8/files/inter-latin-400-normal.woff2',
    ],
  ],
  [
    'name'  => 'Inter-Medium.ttf',
    'label' => 'Inter Medium',
    'urls'  => [
      'https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.8/files/inter-latin-500-normal.woff2',
    ],
  ],
  [
    'name'  => 'PlayfairDisplay-BoldItalic.ttf',
    'label' => 'Playfair Display Bold Italic',
    'urls'  => [
      'https://github.com/google/fonts/raw/main/ofl/playfairdisplay/PlayfairDisplay%5Bwght%5D.ttf',
      'https://fonts.gstatic.com/s/playfairdisplay/v30/nuFvD-vYSZviVYUb_rj3ij__anPXJzDwcbmjWBN2PKdFvUDQZNLo_U2r.woff2',
      'https://cdn.jsdelivr.net/npm/@fontsource/playfair-display@5.0.8/files/playfair-display-latin-700-italic.woff2',
    ],
  ],
];

$results = [];
foreach ($fonts as $font) {
  $path = $dir . $font['name'];
  if (file_exists($path)) {
    $results[] = ['label' => $font['label'], 'status' => 'exists', 'msg' => 'Déjà présente (' . round(filesize($path)/1024) . ' Ko)'];
    continue;
  }
  $status = 'error';
  $lastError = '';
  foreach ($font['urls'] as $url) {
    $result = downloadFont($url, $path);
    if ($result === 'ok') { $status = 'ok'; break; }
    $lastError = $result;
  }
  if ($status === 'ok') {
    $results[] = ['label' => $font['label'], 'status' => 'ok', 'msg' => 'Téléchargée (' . round(filesize($path)/1024) . ' Ko)'];
  } else {
    $results[] = ['label' => $font['label'], 'status' => 'error', 'msg' => 'Échec : ' . $lastError];
  }
}

$allOk = !in_array('error', array_column($results, 'status'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Téléchargement polices — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',sans-serif;background:#FAF8FF;color:#2D1B69;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
    .card{background:#fff;border-radius:16px;padding:2rem;max-width:520px;width:100%;border:.5px solid rgba(139,107,177,.18)}
    h1{font-size:18px;font-weight:500;color:#2D1B69;margin-bottom:1.2rem}
    .result{display:flex;align-items:flex-start;gap:10px;padding:.7rem .9rem;border-radius:10px;margin-bottom:8px;font-size:13px}
    .result.ok{background:#EAF3DE;color:#27500A}
    .result.exists{background:#F0E6FF;color:#2D1B69}
    .result.error{background:#FCEBEB;color:#A32D2D}
    .result-icon{font-size:16px;flex-shrink:0;margin-top:1px}
    .result-body{}
    .result-label{font-weight:500;margin-bottom:2px}
    .result-msg{font-size:12px;opacity:.75}
    .actions{display:flex;gap:10px;margin-top:1.5rem;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:20px;font-size:13px;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;border:none;transition:background .2s}
    .btn-primary{background:#2D1B69;color:#fff}
    .btn-primary:hover{background:#8B6BB1}
    .btn-secondary{background:#F0E6FF;color:#2D1B69}
    .btn-secondary:hover{background:#d4b8f0}
    .summary{font-size:13px;color:#5a4870;margin-bottom:1.2rem;padding:.7rem .9rem;background:#FAF8FF;border-radius:8px;border:.5px solid rgba(139,107,177,.15)}
  </style>
</head>
<body>
  <div class="card">
    <h1>📁 Téléchargement des polices</h1>
    <p class="summary">
      <?php if ($allOk): ?>
        ✓ Toutes les polices sont disponibles. Les cartes de partage utiliseront Playfair Display et Inter.
      <?php else: ?>
        ⚠ Certaines polices n'ont pas pu être téléchargées. Les cartes utiliseront les polices système par défaut.
      <?php endif; ?>
    </p>
    <?php foreach ($results as $r): ?>
    <div class="result <?= $r['status'] ?>">
      <span class="result-icon"><?= $r['status'] === 'ok' ? '✓' : ($r['status'] === 'exists' ? '→' : '✗') ?></span>
      <div class="result-body">
        <p class="result-label"><?= htmlspecialchars($r['label']) ?></p>
        <p class="result-msg"><?= htmlspecialchars($r['msg']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="actions">
      <a href="/admin/index.php" class="btn btn-primary">← Retour au back office</a>
      <?php if (!$allOk): ?>
      <a href="/admin/fonts/download-fonts.php" class="btn btn-secondary">Réessayer</a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
