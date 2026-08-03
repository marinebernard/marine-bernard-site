<?php
function uploadImage($file, $subdir = 'parcours') {
  $allowed = ['jpg', 'jpeg', 'png', 'webp'];
  $maxSize = 10 * 1024 * 1024;
  if ($file['error'] !== UPLOAD_ERR_OK) return null;
  if ($file['size'] > $maxSize) return null;
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $allowed)) return null;
  // L'extension seule ne garantit pas que le fichier est une vraie image
  // (un fichier renommé passerait le test ci-dessus) — on vérifie le
  // contenu réel avant de l'accepter.
  if (@getimagesize($file['tmp_name']) === false) return null;
  $dir = __DIR__ . '/../uploads/' . $subdir . '/';
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $filename = time() . '_' . uniqid() . '.' . $ext;
  if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
    return 'uploads/' . $subdir . '/' . $filename;
  }
  return null;
}

function uploadMultipleImages($files, $subdir = 'parcours') {
  $uploaded = [];
  if (!isset($files['name']) || !is_array($files['name'])) return $uploaded;
  for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
    $file = [
      'name' => $files['name'][$i],
      'type' => $files['type'][$i],
      'tmp_name' => $files['tmp_name'][$i],
      'error' => $files['error'][$i],
      'size' => $files['size'][$i]
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
