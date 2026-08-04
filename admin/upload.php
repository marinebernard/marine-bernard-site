<?php
function optimizeImage($src_path, $dst_path, $max_w = 1600, $max_h = 1200, $quality = 82) {
  $info = @getimagesize($src_path);
  if (!$info) return false;
  $mime = $info['mime'];
  $src = null;
  if ($mime === 'image/jpeg') $src = @imagecreatefromjpeg($src_path);
  elseif ($mime === 'image/png') $src = @imagecreatefrompng($src_path);
  elseif ($mime === 'image/webp') $src = @imagecreatefromwebp($src_path);
  if (!$src) return false;
  $ow = imagesx($src);
  $oh = imagesy($src);
  $ratio = $ow / $oh;
  if ($ow > $max_w || $oh > $max_h) {
    if ($ow / $max_w > $oh / $max_h) {
      $nw = $max_w;
      $nh = (int)($max_w / $ratio);
    } else {
      $nh = $max_h;
      $nw = (int)($max_h * $ratio);
    }
  } else {
    $nw = $ow;
    $nh = $oh;
  }
  $dst = imagecreatetruecolor($nw, $nh);
  // La sortie est toujours un JPEG (pas de canal alpha) — on aplati donc
  // tout fond transparent (PNG) sur du blanc plutôt que de préserver un
  // alpha qui serait de toute façon perdu et laisserait du noir à la place.
  $white = imagecolorallocate($dst, 255, 255, 255);
  imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
  imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
  imagedestroy($src);
  $result = imagejpeg($dst, $dst_path, $quality);
  imagedestroy($dst);
  return $result;
}

function uploadImage($file, $subdir = 'parcours') {
  $allowed = ['jpg', 'jpeg', 'png', 'webp'];
  $maxSize = 15 * 1024 * 1024;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > $maxSize) return null;
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $allowed)) return null;
  // L'extension seule ne garantit pas que le fichier est une vraie image
  // (un fichier renommé passerait le test ci-dessus) — on vérifie le
  // contenu réel avant de l'accepter, avant toute écriture sur le disque.
  if (@getimagesize($file['tmp_name']) === false) return null;

  $dir = __DIR__ . '/../uploads/' . $subdir . '/';
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $tmp = $file['tmp_name'];

  $optimizedName = time() . '_' . uniqid() . '.jpg';
  if (optimizeImage($tmp, $dir . $optimizedName)) {
    return 'uploads/' . $subdir . '/' . $optimizedName;
  }

  // L'image est valide mais l'optimisation a échoué (format exotique,
  // erreur GD…) — on garde l'original plutôt que de perdre l'upload.
  $originalName = time() . '_' . uniqid() . '.' . $ext;
  if (move_uploaded_file($tmp, $dir . $originalName)) {
    return 'uploads/' . $subdir . '/' . $originalName;
  }
  return null;
}

function uploadMultipleImages($files, $subdir = 'parcours') {
  $uploaded = [];
  if (!isset($files['name']) || !is_array($files['name'])) return $uploaded;
  for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
    $file = [
      'name'     => $files['name'][$i],
      'type'     => $files['type'][$i],
      'tmp_name' => $files['tmp_name'][$i],
      'error'    => $files['error'][$i],
      'size'     => $files['size'][$i],
    ];
    $result = uploadImage($file, $subdir);
    if ($result) $uploaded[] = $result;
  }
  return $uploaded;
}

function uploadGPX($file) {
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > 5 * 1024 * 1024) return null;
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if ($ext !== 'gpx') return null;
  $dir = __DIR__ . '/../gpx/';
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $filename = time() . '_' . uniqid() . '.gpx';
  if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
    return 'gpx/' . $filename;
  }
  return null;
}
