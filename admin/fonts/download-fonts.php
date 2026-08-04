<?php
require_once __DIR__ . '/../auth.php';
requireLogin();

$fonts = [
  'Inter-Regular.ttf' => 'https://github.com/rsms/inter/raw/master/docs/font-files/Inter-Regular.otf',
  'Inter-Medium.ttf'  => 'https://github.com/rsms/inter/raw/master/docs/font-files/Inter-Medium.otf',
  'PlayfairDisplay-BoldItalic.ttf' => 'https://github.com/google/fonts/raw/main/ofl/playfairdisplay/PlayfairDisplay-BoldItalic.ttf',
];

$dir = __DIR__ . '/';
$results = [];

foreach ($fonts as $name => $url) {
  $path = $dir . $name;
  if (!file_exists($path)) {
    $content = @file_get_contents($url);
    if ($content) {
      file_put_contents($path, $content);
      $results[] = "✓ $name téléchargée";
    } else {
      $results[] = "✗ Impossible de télécharger $name";
    }
  } else {
    $results[] = "→ $name déjà présente";
  }
}

echo '<pre style="font-family:monospace;padding:2rem">';
foreach ($results as $r) echo $r . "\n";
echo "\n<a href='/admin/index.php'>← Retour au back office</a>";
echo '</pre>';
