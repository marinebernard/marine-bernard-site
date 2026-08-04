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

$w = 1200;
$h = 630;
$img = imagecreatetruecolor($w, $h);
imagealphablending($img, true);
imagesavealpha($img, true);

$violet     = imagecolorallocate($img, 45, 27, 105);
$violet_dark= imagecolorallocate($img, 20, 10, 50);
$lavande    = imagecolorallocate($img, 139, 107, 177);
$or         = imagecolorallocate($img, 201, 169, 110);
$blanc      = imagecolorallocate($img, 255, 255, 255);
$blanc_pale = imagecolorallocate($img, 212, 184, 240);
$blanc_50   = imagecolorallocate($img, 250, 248, 255);
$vert       = imagecolorallocate($img, 39, 80, 10);
$vert_clair = imagecolorallocate($img, 192, 221, 151);
$amber      = imagecolorallocate($img, 250, 199, 117);
$rouge      = imagecolorallocate($img, 240, 149, 149);
$noir_overlay = imagecolorallocatealpha($img, 10, 6, 30, 40);

$col_photo = (int)($w * 0.45);
$col_right = $w - $col_photo;

imagefilledrectangle($img, 0, 0, $w, $h, $violet_dark);

if (!empty($p['photo_principale'])) {
  $photo_path = __DIR__ . '/../' . $p['photo_principale'];
  if (file_exists($photo_path)) {
    $ext = strtolower(pathinfo($photo_path, PATHINFO_EXTENSION));
    $photo_src = null;
    if ($ext === 'jpg' || $ext === 'jpeg') $photo_src = imagecreatefromjpeg($photo_path);
    elseif ($ext === 'png') $photo_src = imagecreatefrompng($photo_path);
    elseif ($ext === 'webp') $photo_src = imagecreatefromwebp($photo_path);

    if ($photo_src) {
      $pw = imagesx($photo_src);
      $ph = imagesy($photo_src);
      $ratio_src = $pw / $ph;
      $ratio_dst = $col_photo / $h;
      if ($ratio_src > $ratio_dst) {
        $new_h = $h;
        $new_w = (int)($h * $ratio_src);
        $src_x = (int)(($new_w - $col_photo) / 2);
        $src_y = 0;
      } else {
        $new_w = $col_photo;
        $new_h = (int)($col_photo / $ratio_src);
        $src_x = 0;
        $src_y = (int)(($new_h - $h) / 2);
      }
      $photo_resized = imagecreatetruecolor($new_w, $new_h);
      imagecopyresampled($photo_resized, $photo_src, 0, 0, 0, 0, $new_w, $new_h, $pw, $ph);
      imagecopy($img, $photo_resized, 0, 0, $src_x, $src_y, $col_photo, $h);
      imagedestroy($photo_src);
      imagedestroy($photo_resized);

      for ($x = 0; $x < $col_photo; $x++) {
        $alpha_start = 80;
        $fade_zone = (int)($col_photo * 0.35);
        $fade_start = $col_photo - $fade_zone;
        if ($x >= $fade_start) {
          $progress = ($x - $fade_start) / $fade_zone;
          $alpha = (int)($alpha_start + (127 - $alpha_start) * $progress);
        } else {
          $alpha = 0;
        }
        if ($alpha > 0) {
          $overlay_col = imagecolorallocatealpha($img, 20, 10, 50, min(127, $alpha));
          imageline($img, $x, 0, $x, $h, $overlay_col);
        }
      }

      $dark_overlay = imagecolorallocatealpha($img, 10, 6, 30, 55);
      imagefilledrectangle($img, 0, 0, $col_photo, $h, $dark_overlay);
    }
  }
}

imagefilledrectangle($img, $col_photo, 0, $w, $h, $violet_dark);

$stripe_col = imagecolorallocatealpha($img, 139, 107, 177, 120);
for ($i = 0; $i < 8; $i++) {
  $cx = $col_photo + 80 + ($i * 140);
  $cy = $h * 0.3 + ($i % 3) * 80;
  $r = 20 + ($i % 3) * 10;
  imagearc($img, $cx, $cy, $r*2, $r*2, 0, 360, $stripe_col);
}

$font_path = __DIR__ . '/fonts/';
$font_regular = $font_path . 'Inter-Regular.ttf';
$font_medium  = $font_path . 'Inter-Medium.ttf';
$font_bold    = $font_path . 'PlayfairDisplay-BoldItalic.ttf';

$use_fonts = file_exists($font_regular) && file_exists($font_bold);

$rx = $col_photo + 50;

if ($use_fonts) {
  $badge_text = strtoupper($p['region'] ?? 'Hauts-de-France');
  imagettftext($img, 11, 0, $rx, 90, $or, $font_medium, $badge_text);

  $titre = $p['titre'] ?? 'Sentier';
  if (mb_strlen($titre) > 32) $titre = mb_substr($titre, 0, 30) . '...';
  imagettftext($img, 30, 0, $rx, 150, $blanc, $font_bold, $titre);

  $sep_y = 210;
} else {
  $badge_text = strtoupper($p['region'] ?? 'Hauts-de-France');
  imagestring($img, 3, $rx, 75, $badge_text, $or);
  imagestring($img, 5, $rx, 120, $p['titre'] ?? 'Sentier', $blanc);
  $sep_y = 180;
}

$sep_col = imagecolorallocatealpha($img, 255, 255, 255, 90);
imageline($img, $rx, $sep_y + 15, $w - 50, $sep_y + 15, $sep_col);

$stats = [
  ['val' => ($p['distance'] ? $p['distance'] . ' km' : '—'), 'label' => 'DISTANCE'],
  ['val' => ($p['duree'] ?: '—'), 'label' => 'DURÉE'],
  ['val' => ($p['denivele'] ? '↑ ' . $p['denivele'] . 'm' : '—'), 'label' => 'DÉNIVELÉ'],
  ['val' => ($p['difficulte'] ? ucfirst($p['difficulte']) : '—'), 'label' => 'DIFFICULTÉ'],
];

$stat_col_w = (int)(($w - $rx - 50) / 2);
$stat_y_start = $sep_y + 45;

foreach ($stats as $i => $stat) {
  $col = $i % 2;
  $row = (int)($i / 2);
  $sx = $rx + ($col * $stat_col_w);
  $sy = $stat_y_start + ($row * 95);

  $val_color = $blanc;
  if ($stat['label'] === 'DIFFICULTÉ') {
    if ($p['difficulte'] === 'facile') $val_color = $vert_clair;
    elseif ($p['difficulte'] === 'modere') $val_color = $amber;
    else $val_color = $rouge;
  }

  if ($use_fonts) {
    imagettftext($img, 24, 0, $sx, $sy + 28, $val_color, $font_bold, $stat['val']);
    imagettftext($img, 10, 0, $sx, $sy + 48, $blanc_pale, $font_medium, $stat['label']);
  } else {
    imagestring($img, 4, $sx, $sy + 10, $stat['val'], $val_color);
    imagestring($img, 2, $sx, $sy + 32, $stat['label'], $blanc_pale);
  }

  if ($i < 2) {
    $dot_col = imagecolorallocatealpha($img, 139, 107, 177, 80);
    imagefilledellipse($img, $sx + 2, $sy + 80, 3, 3, $dot_col);
  }
}

$url_col = imagecolorallocatealpha($img, 250, 248, 255, 90);
if ($use_fonts) {
  imagettftext($img, 11, 0, $rx, $h - 30, $url_col, $font_medium, 'marine-bernard.fr  ✿  @lachtiterandonneuse');
} else {
  imagestring($img, 2, $rx, $h - 45, 'marine-bernard.fr | @lachtiterandonneuse', $blanc_pale);
}

$handle_col = imagecolorallocatealpha($img, 255, 255, 255, 80);
if ($use_fonts) {
  imagettftext($img, 11, 0, 20, $h - 30, $handle_col, $font_medium, '@lachtiterandonneuse');
} else {
  imagestring($img, 2, 20, $h - 45, '@lachtiterandonneuse', $blanc);
}

$output_dir = __DIR__ . '/../uploads/cartes/';
if (!is_dir($output_dir)) mkdir($output_dir, 0755, true);
$filename = 'carte_' . $id . '_' . time() . '.jpg';
$output_path = $output_dir . $filename;

imagejpeg($img, $output_path, 92);
imagedestroy($img);

echo json_encode([
  'success' => true,
  'url' => '/uploads/cartes/' . $filename,
  'filename' => $filename
]);
