<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../api/config.php';
requireLogin();

header('Content-Type: application/json');

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID manquant']); exit; }

$stmt = $pdo->prepare("SELECT * FROM parcours WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); echo json_encode(['error' => 'Parcours non trouvé']); exit; }

$font_dir    = __DIR__ . '/fonts/';
$font_reg    = $font_dir . 'Inter-Regular.ttf';
$font_med    = $font_dir . 'Inter-Medium.ttf';
$font_bold_i = $font_dir . 'PlayfairDisplay-BoldItalic.ttf';
$has_fonts   = file_exists($font_reg) && file_exists($font_med) && file_exists($font_bold_i);

$output_dir = __DIR__ . '/../uploads/cartes/';
if (!is_dir($output_dir)) mkdir($output_dir, 0755, true);

function loadPhoto($path) {
  if (!$path || !file_exists($path)) return null;
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if ($ext === 'jpg' || $ext === 'jpeg') return @imagecreatefromjpeg($path);
  if ($ext === 'png') return @imagecreatefrompng($path);
  if ($ext === 'webp') return @imagecreatefromwebp($path);
  return null;
}

function cropPhoto($src, $dstW, $dstH) {
  if (!$src) return null;
  $sw = imagesx($src); $sh = imagesy($src);
  $ratioSrc = $sw / $sh;
  $ratioDst = $dstW / $dstH;
  if ($ratioSrc > $ratioDst) {
    $newH = $dstH; $newW = (int)($dstH * $ratioSrc);
  } else {
    $newW = $dstW; $newH = (int)($dstW / $ratioSrc);
  }
  $tmp = imagecreatetruecolor($newW, $newH);
  imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newW, $newH, $sw, $sh);
  $dst = imagecreatetruecolor($dstW, $dstH);
  $ox = (int)(($newW - $dstW) / 2);
  $oy = (int)(($newH - $dstH) / 2);
  imagecopy($dst, $tmp, 0, 0, $ox, $oy, $dstW, $dstH);
  imagedestroy($tmp);
  return $dst;
}

function applyGradientOverlay($img, $w, $h, $strength = 0.97) {
  for ($y = 0; $y < $h; $y++) {
    $progress = $y / $h;
    if ($progress < 0.35) {
      $alpha = (int)(115 * ($progress / 0.35));
    } elseif ($progress < 0.65) {
      $alpha = (int)(115 - 90 * (($progress - 0.35) / 0.30));
    } else {
      $alpha = (int)(25 + 102 * (($progress - 0.65) / 0.35) * $strength);
    }
    $alpha = min(127, max(0, $alpha));
    $col = imagecolorallocatealpha($img, 10, 6, 30, $alpha);
    imageline($img, 0, $y, $w, $y, $col);
  }
}

function drawFlower($img, $x, $y, $size = 22) {
  $violet = imagecolorallocatealpha($img, 139, 107, 177, 10);
  $or     = imagecolorallocatealpha($img, 201, 169, 110, 10);
  $center = imagecolorallocate($img, 45, 27, 105);
  $white  = imagecolorallocate($img, 250, 248, 255);
  $s = $size / 22;
  imagefilledellipse($img, $x, $y - (int)(8*$s), (int)(10*$s), (int)(15*$s), $violet);
  imagefilledellipse($img, $x, $y + (int)(8*$s), (int)(10*$s), (int)(15*$s), $violet);
  imagefilledellipse($img, $x - (int)(8*$s), $y, (int)(15*$s), (int)(10*$s), $or);
  imagefilledellipse($img, $x + (int)(8*$s), $y, (int)(15*$s), (int)(10*$s), $or);
  imagefilledellipse($img, $x, $y, (int)(14*$s), (int)(14*$s), $center);
  imagefilledellipse($img, $x, $y, (int)(9*$s), (int)(9*$s), $white);
}

function drawDot($img, $x, $y, $col) {
  imagefilledellipse($img, $x, $y, 6, 6, $col);
}

function getDiffColor($img, $diff) {
  if ($diff === 'facile')    return ['val' => imagecolorallocate($img, 192, 221, 151), 'label' => '● Facile'];
  if ($diff === 'modere')    return ['val' => imagecolorallocate($img, 250, 199, 117), 'label' => '● Modéré'];
  if ($diff === 'difficile') return ['val' => imagecolorallocate($img, 240, 149, 149), 'label' => '● Difficile'];
  return ['val' => imagecolorallocate($img, 192, 221, 151), 'label' => '● —'];
}

function drawStatBlock($img, $x, $y, $w, $h, $val, $label, $valColor, $labelColor, $font_bold, $font_reg, $hasFonts) {
  $bg = imagecolorallocatealpha($img, 255, 255, 255, 102);
  $border = imagecolorallocatealpha($img, 255, 255, 255, 110);
  imagefilledrectangle($img, $x, $y, $x+$w, $y+$h, $bg);
  imagerectangle($img, $x, $y, $x+$w, $y+$h, $border);
  if ($hasFonts) {
    imagettftext($img, 18, 0, $x+14, $y+28, $valColor, $font_bold, $val);
    imagettftext($img, 9, 0, $x+14, $y+42, $labelColor, $font_reg, strtoupper($label));
  } else {
    imagestring($img, 4, $x+10, $y+8, $val, $valColor);
    imagestring($img, 1, $x+10, $y+26, strtoupper($label), $labelColor);
  }
}

$photo_src = loadPhoto(__DIR__ . '/../' . ($p['photo_principale'] ?? ''));

$cards = [
  'facebook' => ['w' => 1200, 'h' => 630],
  'instagram' => ['w' => 1080, 'h' => 1080],
];

$generated = [];

foreach ($cards as $format => $dims) {
  $W = $dims['w'];
  $H = $dims['h'];
  $img = imagecreatetruecolor($W, $H);
  imagealphablending($img, true);
  imagesavealpha($img, true);

  $bg_dark = imagecolorallocate($img, 20, 10, 40);
  imagefilledrectangle($img, 0, 0, $W, $H, $bg_dark);

  if ($photo_src) {
    $photo = cropPhoto($photo_src, $W, $H);
    if ($photo) {
      imagecopy($img, $photo, 0, 0, 0, 0, $W, $H);
      imagedestroy($photo);
    }
  } else {
    $grad1 = imagecolorallocate($img, 12, 68, 124);
    $grad2 = imagecolorallocate($img, 39, 80, 10);
    for ($y = 0; $y < $H; $y++) {
      $r = (int)(12 + ($y / $H) * 27);
      $g = (int)(68 + ($y / $H) * 12);
      $b = (int)(124 - ($y / $H) * 114);
      $col = imagecolorallocate($img, $r, $g, $b);
      imageline($img, 0, $y, $W, $y, $col);
    }
  }

  applyGradientOverlay($img, $W, $H);

  $blanc      = imagecolorallocate($img, 255, 255, 255);
  $blanc_70   = imagecolorallocatealpha($img, 255, 255, 255, 38);
  $blanc_40   = imagecolorallocatealpha($img, 255, 255, 255, 77);
  $or         = imagecolorallocate($img, 201, 169, 110);
  $or_pale    = imagecolorallocatealpha($img, 201, 169, 110, 60);
  $blanc_pale = imagecolorallocatealpha($img, 255, 255, 255, 90);
  $blanc_25   = imagecolorallocatealpha($img, 255, 255, 255, 102);
  $diff       = getDiffColor($img, $p['difficulte'] ?? '');

  $pad = $format === 'instagram' ? 36 : 40;
  $bottom_pad = $format === 'instagram' ? 30 : 28;

  drawFlower($img, $W - $pad - 10, $pad + 8, $format === 'instagram' ? 26 : 24);

  $dot_col = imagecolorallocate($img, 201, 169, 110);
  drawDot($img, $pad, $pad + 10, $dot_col);
  $handle = '@lachtiterandonneuse';
  if ($has_fonts) {
    imagettftext($img, 12, 0, $pad + 14, $pad + 16, $blanc_70, $font_reg, $handle);
  } else {
    imagestring($img, 2, $pad + 14, $pad, $handle, $blanc_70);
  }

  $stats_h  = 58;
  $stat_count = 4;
  $stat_gap   = 4;
  $total_stat_w = $W - ($pad * 2);
  $stat_w = (int)(($total_stat_w - ($stat_count - 1) * $stat_gap) / $stat_count);

  $region_text = strtoupper($p['region'] ?? 'Hauts-de-France');
  $titre = $p['titre'] ?? 'Sentier';
  if (mb_strlen($titre) > 45) $titre = mb_substr($titre, 0, 43) . '...';

  $titre_size = $format === 'instagram' ? 32 : 34;
  $titre_h    = $format === 'instagram' ? 80 : 72;

  $url_y      = $H - $bottom_pad;
  $stats_y    = $url_y - $stats_h - 14;
  $titre_y    = $stats_y - $titre_h;
  $region_y   = $titre_y - 28;
  $sep_y      = $region_y - 14;

  imageline($img, $pad, $sep_y, $W - $pad, $sep_y, $blanc_25);

  $region_bg = imagecolorallocatealpha($img, 201, 169, 110, 100);
  $region_border = imagecolorallocatealpha($img, 201, 169, 110, 70);
  imagefilledrectangle($img, $pad, $region_y - 20, $pad + 260, $region_y + 4, $region_bg);

  if ($has_fonts) {
    imagettftext($img, 10, 0, $pad + 10, $region_y, $or, $font_med, '📍 ' . $region_text);
  } else {
    imagestring($img, 2, $pad + 8, $region_y - 16, $region_text, $or);
  }

  if ($has_fonts) {
    imagettftext($img, $titre_size, 0, $pad, $titre_y + $titre_h - 10, $blanc, $font_bold_i, $titre);
  } else {
    imagestring($img, 5, $pad, $titre_y, $titre, $blanc);
  }

  $label_col = imagecolorallocatealpha($img, 255, 255, 255, 80);
  $stats = [
    ['val' => ($p['distance'] ? $p['distance'] . ' km' : '—'), 'label' => 'Distance'],
    ['val' => ($p['duree'] ?: '—'),                            'label' => 'Durée'],
    ['val' => ($p['denivele'] ? '↑ ' . $p['denivele'] . 'm' : '—'), 'label' => 'Dénivelé'],
    ['val' => $diff['label'],                                  'label' => 'Difficulté', 'color' => $diff['val']],
  ];

  foreach ($stats as $i => $stat) {
    $sx = $pad + $i * ($stat_w + $stat_gap);
    $valColor = isset($stat['color']) ? $stat['color'] : $blanc;
    drawStatBlock($img, $sx, $stats_y, $stat_w, $stats_h, $stat['val'], $stat['label'], $valColor, $label_col, $font_bold_i, $font_reg, $has_fonts);
  }

  $url_text = 'marine-bernard.fr';
  $gpx_text = $p['fichier_gpx'] ? '  ·  Trace GPX disponible' : '';
  if ($has_fonts) {
    imagettftext($img, 11, 0, $pad, $url_y, $blanc_pale, $font_reg, $url_text . $gpx_text);
  } else {
    imagestring($img, 2, $pad, $url_y - 14, $url_text . $gpx_text, $blanc_pale);
  }

  $filename = $format . '_' . $id . '_' . time() . '.jpg';
  $filepath = $output_dir . $filename;
  imagejpeg($img, $filepath, 94);
  imagedestroy($img);

  $generated[$format] = [
    'url'      => '/uploads/cartes/' . $filename,
    'filename' => $filename,
    'label'    => $format === 'facebook' ? 'Facebook / LinkedIn 1200×630' : 'Instagram 1080×1080',
  ];
}

if ($photo_src) imagedestroy($photo_src);

echo json_encode(['success' => true, 'cards' => $generated]);
