<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
  header('Location: /admin/index.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (login($_POST['password'] ?? '')) {
    header('Location: /admin/index.php');
    exit;
  } else {
    $error = 'Mot de passe incorrect.';
  }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Marine Bernard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%232D1B69'/><circle cx='16' cy='16' r='4' fill='%23FAF8FF'/><ellipse cx='16' cy='7' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='16' cy='25' rx='3' ry='4.5' fill='%238B6BB1' opacity='.9'/><ellipse cx='7' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='25' cy='16' rx='4.5' ry='3' fill='%23C9A96E' opacity='.85'/><ellipse cx='9.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 9.5 9.5)'/><ellipse cx='22.5' cy='9.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 22.5 9.5)'/><ellipse cx='9.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(45 9.5 22.5)'/><ellipse cx='22.5' cy='22.5' rx='2.8' ry='4' fill='%23d4b8f0' opacity='.8' transform='rotate(-45 22.5 22.5)'/><circle cx='16' cy='16' r='2.5' fill='%232D1B69'/></svg>">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#2D1B69;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif}
    .card{background:#fff;border-radius:20px;padding:2.5rem;width:100%;max-width:380px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
    .flower{animation:spin 8s linear infinite;margin-bottom:1rem}
    @keyframes spin{to{transform:rotate(360deg)}}
    h1{font-family:'Playfair Display',serif;font-style:italic;font-size:22px;color:#2D1B69;margin-bottom:.3rem}
    .sub{font-size:13px;color:#9a88b8;margin-bottom:1.5rem}
    .field{width:100%;border:.5px solid rgba(139,107,177,.3);border-radius:10px;padding:11px 14px;font-size:14px;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;margin-bottom:1rem}
    .field:focus{border-color:#8B6BB1}
    .btn{width:100%;background:#2D1B69;color:#fff;border:none;border-radius:22px;padding:12px;font-size:14px;font-family:'Inter',sans-serif;cursor:pointer;transition:background .2s}
    .btn:hover{background:#8B6BB1}
    .error{background:#fef2f2;color:#dc2626;border-radius:8px;padding:.6rem;font-size:13px;margin-bottom:1rem}
    .site-link{display:block;margin-top:1rem;font-size:12px;color:#9a88b8;text-decoration:none}
    .site-link:hover{color:#2D1B69}
  </style>
</head>
<body>
  <div class="card">
    <svg class="flower" width="44" height="44" viewBox="0 0 44 44">
      <circle cx="22" cy="22" r="6" fill="#2D1B69"/>
      <ellipse cx="22" cy="8" rx="4" ry="7" fill="#8B6BB1" opacity=".9"/>
      <ellipse cx="22" cy="36" rx="4" ry="7" fill="#8B6BB1" opacity=".9"/>
      <ellipse cx="8" cy="22" rx="7" ry="4" fill="#C9A96E" opacity=".85"/>
      <ellipse cx="36" cy="22" rx="7" ry="4" fill="#C9A96E" opacity=".85"/>
      <ellipse cx="12" cy="12" rx="4" ry="6" fill="#d4b8f0" opacity=".8" transform="rotate(-45 12 12)"/>
      <ellipse cx="32" cy="12" rx="4" ry="6" fill="#d4b8f0" opacity=".8" transform="rotate(45 32 12)"/>
      <ellipse cx="12" cy="32" rx="4" ry="6" fill="#d4b8f0" opacity=".8" transform="rotate(45 12 32)"/>
      <ellipse cx="32" cy="32" rx="4" ry="6" fill="#d4b8f0" opacity=".8" transform="rotate(-45 32 32)"/>
      <circle cx="22" cy="22" r="4" fill="#FAF8FF"/>
    </svg>
    <h1>Espace admin</h1>
    <p class="sub">marine-bernard.fr</p>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="password" name="password" class="field" placeholder="Mot de passe" required autofocus>
      <button type="submit" class="btn">Se connecter</button>
    </form>
    <a href="/" class="site-link">← Retour au site</a>
  </div>
</body>
</html>
