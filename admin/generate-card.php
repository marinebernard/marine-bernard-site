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

$W = 1080;
$H = 1080;

$font_dir    = __DIR__ . '/fonts/';
$font_reg    = $font_dir . 'Inter-Regular.ttf';
$font_med    = $font_dir . 'Inter-Medium.ttf';
$font_bold_i = $font_dir . 'PlayfairDisplay-BoldItalic.ttf';
$has_fonts   = file_exists($font_reg) && file_exists($font_bold_i) && file_exists($font_med);

function mb_convert_for_gd($text) {
  $map = [
    'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
    'à'=>'a','â'=>'a','ä'=>'a','á'=>'a',
    'î'=>'i','ï'=>'i','í'=>'i','ì'=>'i',
    'ô'=>'o','ö'=>'o','ó'=>'o','ò'=>'o',
    'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
    'ç'=>'c','ñ'=>'n',
    'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
    'À'=>'A','Â'=>'A','Ä'=>'A',
    'Î'=>'I','Ï'=>'I',
    'Ô'=>'O','Ö'=>'O',
    'Ù'=>'U','Û'=>'U','Ü'=>'U',
    'Ç'=>'C','Ñ'=>'N',
    '’'=>"'",'‘'=>"'",'“'=>'"','”'=>'"','–'=>'-','—'=>'-',
  ];
  return strtr($text, $map);
}

function safe_text($text, $has_fonts) {
  if (!$has_fonts) return mb_convert_for_gd($text);
  return $text;
}

function wrap_text($text, $max_chars) {
  if (mb_strlen($text) <= $max_chars) return [$text];
  $words = explode(' ', $text);
  $lines = [];
  $current = '';
  foreach ($words as $word) {
    $test = $current ? $current . ' ' . $word : $word;
    if (mb_strlen($test) <= $max_chars) {
      $current = $test;
    } else {
      if ($current) $lines[] = $current;
      $current = $word;
    }
  }
  if ($current) $lines[] = $current;
  return array_slice($lines, 0, 3);
}

$img = imagecreatetruecolor($W, $H);
imagealphablending($img, true);
imagesavealpha($img, true);

$bg = imagecolorallocate($img, 20, 10, 40);
imagefilledrectangle($img, 0, 0, $W, $H, $bg);

$photo_path = !empty($p['photo_principale']) ? __DIR__ . '/../' . $p['photo_principale'] : null;
if ($photo_path && file_exists($photo_path)) {
  $ext = strtolower(pathinfo($photo_path, PATHINFO_EXTENSION));
  $src = null;
  if (in_array($ext, ['jpg','jpeg'])) $src = @imagecreatefromjpeg($photo_path);
  elseif ($ext === 'png') $src = @imagecreatefrompng($photo_path);
  elseif ($ext === 'webp') $src = @imagecreatefromwebp($photo_path);

  if ($src) {
    $sw = imagesx($src); $sh = imagesy($src);
    $ratio = $sw / $sh;
    if ($ratio > 1) { $nw = (int)($H * $ratio); $nh = $H; }
    else { $nw = $W; $nh = (int)($W / $ratio); }
    $tmp = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
    $ox = (int)(($nw - $W) / 2);
    $oy = (int)(($nh - $H) / 2);
    imagecopy($img, $tmp, 0, 0, $ox, $oy, $W, $H);
    imagedestroy($src);
    imagedestroy($tmp);
  }
} else {
  for ($y = 0; $y < $H; $y++) {
    $t = $y / $H;
    $r = (int)(27 + $t * 12);
    $g = (int)(80 + $t * (-80 + 10));
    $b_c = (int)(10 + $t * 30);
    $col = imagecolorallocate($img, $r, max(0,$g), $b_c);
    imageline($img, 0, $y, $W, $y, $col);
  }
}

for ($y = 0; $y < $H; $y++) {
  $t = $y / $H;
  $alpha = 0;
  if ($t < 0.25) $alpha = (int)(20 * ($t / 0.25));
  elseif ($t < 0.55) $alpha = (int)(20 + 30 * (($t - 0.25) / 0.30));
  else $alpha = (int)(50 + 77 * (($t - 0.55) / 0.45));
  $alpha = min(127, max(0, $alpha));
  $oc = imagecolorallocatealpha($img, 10, 6, 30, $alpha);
  imageline($img, 0, $y, $W, $y, $oc);
}

$pad = 60;

$blanc       = imagecolorallocate($img, 255, 255, 255);
$blanc_soft  = imagecolorallocatealpha($img, 255, 255, 255, 25);
$or          = imagecolorallocate($img, 201, 169, 110);
$or_bg       = imagecolorallocatealpha($img, 201, 169, 110, 85);
$vert_clair  = imagecolorallocate($img, 192, 221, 151);
$amber       = imagecolorallocate($img, 250, 199, 117);
$rouge_clair = imagecolorallocate($img, 240, 149, 149);
$gris_pale   = imagecolorallocatealpha($img, 255, 255, 255, 55);
$noir_soft   = imagecolorallocatealpha($img, 0, 0, 0, 30);

$diff_colors = ['facile' => $vert_clair, 'modere' => $amber, 'difficile' => $rouge_clair];
$diff_labels = ['facile' => 'Facile', 'modere' => 'Modere', 'difficile' => 'Difficile'];
$diff_key    = $p['difficulte'] ?? '';
$diff_color  = $diff_colors[$diff_key] ?? $vert_clair;
$diff_label  = $diff_labels[$diff_key] ?? '';

function draw_flower_gd($img, $cx, $cy, $size, $violet, $or, $white) {
  $s = $size / 11;
  imagefilledellipse($img, $cx, $cy - (int)(8*$s), (int)(10*$s), (int)(15*$s), $violet);
  imagefilledellipse($img, $cx, $cy + (int)(8*$s), (int)(10*$s), (int)(15*$s), $violet);
  imagefilledellipse($img, $cx - (int)(8*$s), $cy, (int)(15*$s), (int)(10*$s), $or);
  imagefilledellipse($img, $cx + (int)(8*$s), $cy, (int)(15*$s), (int)(10*$s), $or);
  imagefilledellipse($img, $cx, $cy, (int)(14*$s), (int)(14*$s), $violet);
  imagefilledellipse($img, $cx, $cy, (int)(8*$s), (int)(8*$s), $white);
}

$violet_f = imagecolorallocate($img, 139, 107, 177);
$white_f  = imagecolorallocate($img, 250, 248, 255);
draw_flower_gd($img, $W - $pad - 5, $pad + 5, 28, $violet_f, $or, $white_f);

$dot = imagecolorallocate($img, 201, 169, 110);
imagefilledellipse($img, $pad, $pad + 12, 8, 8, $dot);
$handle = '@lachtiterandonneuse';
if ($has_fonts) {
  imagettftext($img, 16, 0, $pad + 16, $pad + 18, $blanc_soft, $font_med, $handle);
} else {
  imagestring($img, 3, $pad + 16, $pad + 4, $handle, $blanc_soft);
}

$titre_raw = $p['titre'] ?? 'Sentier';
$titre_lines = wrap_text($titre_raw, 28);
$region_raw = strtoupper($p['region'] ?? 'Hauts-de-France');

$stats = [
  ['ico'=>'km',   'val'=> ($p['distance'] ? $p['distance'].' km' : '-'),         'lbl'=>'Distance'],
  ['ico'=>'time', 'val'=> ($p['duree'] ?: '-'),                                   'lbl'=>'Duree'],
  ['ico'=>'up',   'val'=> ($p['denivele'] ? '+'.$p['denivele'].'m' : '-'),        'lbl'=>'Denivele'],
  ['ico'=>'diff', 'val'=> $diff_label,                                            'lbl'=>'Difficulte', 'color'=>$diff_color],
];

$stat_h    = 110;
$stat_w    = (int)(($W - $pad*2 - 12) / 4);
$stats_y   = $H - $pad - $stat_h;
$titre_y   = $stats_y - 30;
$region_y  = $titre_y - (count($titre_lines) * 70) - 20;
$line_y    = $region_y - 20;

$line_col = imagecolorallocatealpha($img, 255, 255, 255, 80);
imageline($img, $pad, $line_y, $W - $pad, $line_y, $line_col);

$reg_bg = imagecolorallocatealpha($img, 30, 20, 80, 60);
imagefilledrectangle($img, $pad, $line_y + 12, $pad + 340, $line_y + 46, $reg_bg);
if ($has_fonts) {
  imagettftext($img, 14, 0, $pad + 12, $line_y + 36, $or, $font_med, safe_text($region_raw, $has_fonts));
} else {
  imagestring($img, 3, $pad + 10, $line_y + 16, mb_convert_for_gd($region_raw), $or);
}

$ty = $region_y + 20;
foreach ($titre_lines as $line) {
  $shadow = imagecolorallocatealpha($img, 0, 0, 0, 60);
  if ($has_fonts) {
    imagettftext($img, 52, 0, $pad + 2, $ty + 2, $shadow, $font_bold_i, safe_text($line, $has_fonts));
    imagettftext($img, 52, 0, $pad, $ty, $blanc, $font_bold_i, safe_text($line, $has_fonts));
  } else {
    imagestring($img, 5, $pad, $ty - 52, mb_convert_for_gd($line), $blanc);
  }
  $ty += 68;
}

for ($i = 0; $i < 4; $i++) {
  $sx = $pad + $i * ($stat_w + 4);
  $sy = $stats_y;
  $stat_bg = imagecolorallocatealpha($img, 15, 8, 40, 35);
  imagefilledrectangle($img, $sx, $sy, $sx + $stat_w, $sy + $stat_h, $stat_bg);
  $border = imagecolorallocatealpha($img, 255, 255, 255, 95);
  imagerectangle($img, $sx, $sy, $sx + $stat_w, $sy + $stat_h, $border);

  $val_color = isset($stats[$i]['color']) ? $stats[$i]['color'] : $blanc;
  $val_text  = safe_text($stats[$i]['val'], $has_fonts);
  $lbl_text  = safe_text($stats[$i]['lbl'], $has_fonts);

  if ($has_fonts) {
    $shadow2 = imagecolorallocatealpha($img, 0, 0, 0, 50);
    imagettftext($img, 22, 0, $sx + 14, $sy + 44, $shadow2, $font_bold_i, $val_text);
    imagettftext($img, 22, 0, $sx + 12, $sy + 42, $val_color, $font_bold_i, $val_text);
    imagettftext($img, 13, 0, $sx + 12, $sy + 68, $gris_pale, $font_med, strtoupper($lbl_text));
  } else {
    imagestring($img, 4, $sx + 10, $sy + 18, mb_convert_for_gd($val_text), $val_color);
    imagestring($img, 2, $sx + 10, $sy + 42, mb_convert_for_gd(strtoupper($lbl_text)), $gris_pale);
  }
}

$url_col = imagecolorallocatealpha($img, 255, 255, 255, 80);
$url_text = 'marine-bernard.fr';
$gpx_text = $p['fichier_gpx'] ? '  |  Trace GPX disponible' : '';
if ($has_fonts) {
  imagettftext($img, 14, 0, $pad, $H - $pad + 28, $url_col, $font_reg, $url_text . $gpx_text);
} else {
  imagestring($img, 2, $pad, $H - $pad + 10, $url_text . $gpx_text, $url_col);
}

$output_dir = __DIR__ . '/../uploads/cartes/';
if (!is_dir($output_dir)) mkdir($output_dir, 0755, true);
$filename = 'instagram_' . $id . '_' . time() . '.jpg';
imagejpeg($img, $output_dir . $filename, 95);
imagedestroy($img);

echo json_encode([
  'success'  => true,
  'cards'    => [
    'instagram' => [
      'url'      => '/uploads/cartes/' . $filename,
      'filename' => $filename,
      'label'    => 'Instagram 1080×1080',
    ]
  ]
]);
