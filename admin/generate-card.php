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

/* imageopenpolygon() n'existe qu'à partir de PHP 8.0 — on relie les points
   avec des lignes pour un résultat identique sans risquer une erreur fatale
   si l'hébergeur tourne encore sous PHP 7. */
function draw_open_polyline($img, $pts, $col) {
  $n = count($pts) / 2;
  for ($i = 0; $i < $n - 1; $i++) {
    imageline($img, $pts[$i*2], $pts[$i*2+1], $pts[($i+1)*2], $pts[($i+1)*2+1], $col);
  }
}

function draw_icon_km($img, $cx, $cy, $size, $col) {
  $s = $size / 10;
  imagesetthickness($img, max(1, (int)(2*$s)));
  $r = (int)(8*$s);
  imagearc($img, $cx, $cy, $r*2, $r*2, 0, 360, $col);
  imageline($img, $cx, $cy - (int)(11*$s), $cx, $cy - (int)(7*$s), $col);
  imageline($img, $cx - (int)(4*$s), $cy, $cx + (int)(4*$s), $cy, $col);
  imagesetthickness($img, 1);
}

function draw_icon_time($img, $cx, $cy, $size, $col) {
  $s = $size / 10;
  imagesetthickness($img, max(1, (int)(2*$s)));
  $r = (int)(9*$s);
  imagearc($img, $cx, $cy, $r*2, $r*2, 0, 360, $col);
  imageline($img, $cx, $cy, $cx, $cy - (int)(6*$s), $col);
  imageline($img, $cx, $cy, $cx + (int)(4*$s), $cy + (int)(2*$s), $col);
  imagesetthickness($img, 1);
}

function draw_icon_up($img, $cx, $cy, $size, $col) {
  $s = $size / 10;
  imagesetthickness($img, max(1, (int)(2*$s)));
  $pts = [
    $cx, $cy + (int)(8*$s),
    $cx - (int)(6*$s), $cy + (int)(8*$s),
    $cx - (int)(6*$s), $cy,
    $cx, $cy - (int)(8*$s),
    $cx + (int)(6*$s), $cy,
    $cx + (int)(6*$s), $cy + (int)(8*$s),
  ];
  draw_open_polyline($img, $pts, $col);
  imageline($img, $cx - (int)(3*$s), $cy - (int)(3*$s), $cx, $cy - (int)(8*$s), $col);
  imageline($img, $cx + (int)(3*$s), $cy - (int)(3*$s), $cx, $cy - (int)(8*$s), $col);
  imagesetthickness($img, 1);
}

function draw_icon_diff($img, $cx, $cy, $size, $col) {
  $s = $size / 10;
  imagesetthickness($img, max(1, (int)(2*$s)));
  $pts = [
    $cx, $cy - (int)(9*$s),
    $cx + (int)(8*$s), $cy + (int)(6*$s),
    $cx - (int)(8*$s), $cy + (int)(6*$s),
  ];
  draw_open_polyline($img, $pts, $col);
  imageline($img, $cx, $cy - (int)(4*$s), $cx, $cy + (int)(2*$s), $col);
  imagefilledellipse($img, $cx, $cy + (int)(5*$s), (int)(3*$s), (int)(3*$s), $col);
  imagesetthickness($img, 1);
}

function draw_icon_balise($img, $cx, $cy, $size, $col) {
  $s = $size / 10;
  imagesetthickness($img, max(1, (int)(2*$s)));
  imageline($img, $cx - (int)(2*$s), $cy - (int)(9*$s), $cx - (int)(2*$s), $cy + (int)(9*$s), $col);
  $pts_flag = [
    $cx - (int)(2*$s), $cy - (int)(9*$s),
    $cx + (int)(9*$s), $cy - (int)(5*$s),
    $cx - (int)(2*$s), $cy - (int)(1*$s),
  ];
  imagefilledpolygon($img, $pts_flag, $col);
  imagesetthickness($img, 1);
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

$balisage_colors = [
  'jaune'       => [255, 220, 50],
  'rouge'       => [220, 60, 60],
  'rouge_blanc' => [220, 60, 60],
  'orange'      => [230, 140, 40],
  'bleu'        => [60, 130, 220],
  'vert'        => [60, 180, 80],
  'mixte'       => [180, 100, 220],
  'autre'       => [160, 160, 160],
];

$balisage_labels = [
  'jaune'       => 'Jaune PR',
  'rouge'       => 'Rouge GRP',
  'rouge_blanc' => 'Rouge/Blanc GR',
  'orange'      => 'Orange',
  'bleu'        => 'Bleu ONF',
  'vert'        => 'Vert',
  'mixte'       => 'Mixte',
  'autre'       => 'Balisage local',
];

$has_balisage = !empty($p['balisage']) && !empty($p['type_balisage']);
$balise_type  = $p['type_balisage'] ?? '';
$balise_rgb   = $has_balisage && isset($balisage_colors[$balise_type]) ? $balisage_colors[$balise_type] : null;
$balise_label = $has_balisage && isset($balisage_labels[$balise_type]) ? $balisage_labels[$balise_type] : 'Balisé';

$stats = [
  ['ico'=>'km',   'val'=> ($p['distance'] ? $p['distance'].' km' : '-'),  'lbl'=>'Distance',  'draw'=>'draw_icon_km'],
  ['ico'=>'time', 'val'=> ($p['duree'] ?: '-'),                            'lbl'=>'Duree',     'draw'=>'draw_icon_time'],
  ['ico'=>'up',   'val'=> ($p['denivele'] ? '+'.$p['denivele'].'m' : '-'),'lbl'=>'Denivele',  'draw'=>'draw_icon_up'],
  ['ico'=>'diff', 'val'=> $diff_label,                                     'lbl'=>'Difficulte','draw'=>'draw_icon_diff', 'color'=>$diff_color],
];

$nb_stats   = 4;
$stat_gap   = 8;
$stat_h     = 120;
$stat_w     = (int)(($W - $pad * 2 - $stat_gap * ($nb_stats - 1)) / $nb_stats);
$stats_y    = $H - $pad - $stat_h - ($has_balisage ? 80 : 0);
$titre_y    = $stats_y - 30;
$region_y   = $titre_y - (count($titre_lines) * 70) - 20;
$line_y     = $region_y - 20;

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

for ($i = 0; $i < $nb_stats; $i++) {
  $sx = $pad + $i * ($stat_w + $stat_gap);
  $sy = $stats_y;

  $stat_bg = imagecolorallocatealpha($img, 10, 5, 30, 30);
  imagefilledrectangle($img, $sx, $sy, $sx + $stat_w, $sy + $stat_h, $stat_bg);
  $border = imagecolorallocatealpha($img, 255, 255, 255, 95);
  imagerectangle($img, $sx, $sy, $sx + $stat_w, $sy + $stat_h, $border);

  $val_color = isset($stats[$i]['color']) ? $stats[$i]['color'] : $blanc;
  $icon_col  = imagecolorallocatealpha($img, 255, 255, 255, 55);
  $cx_icon   = $sx + $stat_w - 22;
  $cy_icon   = $sy + 22;
  $icon_size = 10;

  switch ($stats[$i]['ico']) {
    case 'km':   draw_icon_km($img,   $cx_icon, $cy_icon, $icon_size, $icon_col); break;
    case 'time': draw_icon_time($img, $cx_icon, $cy_icon, $icon_size, $icon_col); break;
    case 'up':   draw_icon_up($img,   $cx_icon, $cy_icon, $icon_size, $icon_col); break;
    case 'diff': draw_icon_diff($img, $cx_icon, $cy_icon, $icon_size, $icon_col); break;
  }

  $val_text = safe_text($stats[$i]['val'], $has_fonts);
  $lbl_text = safe_text($stats[$i]['lbl'], $has_fonts);

  if ($has_fonts) {
    $shadow2 = imagecolorallocatealpha($img, 0, 0, 0, 55);
    imagettftext($img, 24, 0, $sx + 14, $sy + 52, $shadow2, $font_bold_i, $val_text);
    imagettftext($img, 24, 0, $sx + 12, $sy + 50, $val_color, $font_bold_i, $val_text);
    imagettftext($img, 12, 0, $sx + 12, $sy + 74, $gris_pale, $font_med, strtoupper($lbl_text));
  } else {
    imagestring($img, 4, $sx + 10, $sy + 22, mb_convert_for_gd($val_text), $val_color);
    imagestring($img, 2, $sx + 10, $sy + 46, mb_convert_for_gd(strtoupper($lbl_text)), $gris_pale);
  }
}

if ($has_balisage) {
  $balise_y  = $stats_y + $stat_h + 14;
  $balise_bh = 56;

  if ($balise_rgb) {
    $bc = imagecolorallocate($img, $balise_rgb[0], $balise_rgb[1], $balise_rgb[2]);
  } else {
    $bc = $or;
  }

  $balise_bg_dark = imagecolorallocatealpha($img, 10, 5, 30, 30);
  imagefilledrectangle($img, $pad, $balise_y, $W - $pad, $balise_y + $balise_bh, $balise_bg_dark);

  $balise_border = imagecolorallocatealpha($img, 255, 255, 255, 95);
  imagerectangle($img, $pad, $balise_y, $W - $pad, $balise_y + $balise_bh, $balise_border);

  $color_dot_x = $pad + 28;
  $color_dot_y = $balise_y + (int)($balise_bh / 2);
  imagefilledellipse($img, $color_dot_x, $color_dot_y, 22, 22, $bc);
  $dot_border = imagecolorallocatealpha($img, 255, 255, 255, 60);
  imagearc($img, $color_dot_x, $color_dot_y, 22, 22, 0, 360, $dot_border);

  draw_icon_balise($img, $color_dot_x, $color_dot_y, 8, imagecolorallocate($img, 255,255,255));

  $balise_text_x = $pad + 50;
  $balise_label_safe = safe_text('Balisage ' . $balise_label, $has_fonts);

  if ($has_fonts) {
    imagettftext($img, 13, 0, $balise_text_x, $balise_y + 24, $gris_pale, $font_med, strtoupper('Balisage'));
    imagettftext($img, 18, 0, $balise_text_x, $balise_y + 46, $bc, $font_bold_i, $balise_label_safe);
  } else {
    imagestring($img, 2, $balise_text_x, $balise_y + 6, 'BALISAGE', $gris_pale);
    imagestring($img, 4, $balise_text_x, $balise_y + 24, mb_convert_for_gd($balise_label), $bc);
  }

  $non_perdu_x = $W - $pad - 220;
  $non_perdu_safe = safe_text('Ne jamais se perdre', $has_fonts);
  if ($has_fonts) {
    imagettftext($img, 13, 0, $non_perdu_x, $balise_y + 24, $gris_pale, $font_med, strtoupper('Trace GPS'));
    imagettftext($img, 16, 0, $non_perdu_x, $balise_y + 46, $blanc_soft, $font_bold_i, $non_perdu_safe);
  } else {
    imagestring($img, 2, $non_perdu_x, $balise_y + 6, 'TRACE GPS', $gris_pale);
    imagestring($img, 3, $non_perdu_x, $balise_y + 24, 'Ne jamais se perdre', $blanc_soft);
  }

  $sep_x = (int)(($pad + $non_perdu_x) / 2);
  imageline($img, $sep_x, $balise_y + 10, $sep_x, $balise_y + $balise_bh - 10, $balise_border);
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
