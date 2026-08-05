<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../api/config.php';
requireLogin();

$pdo = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
  DB_USER,
  DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$projets = [
  ['titre'=>'Refonte site Jouvence','slug'=>'refonte-jouvence','categorie'=>'ecommerce','client'=>'Jouvence','annee'=>2023,'description'=>'Refonte complète de 3 sites e-commerce WordPress et PrestaShop.','mission'=>'UX/UI, charte graphique, intégration, photographie produit.','outils'=>'["Figma","Photoshop","WordPress","PrestaShop"]','url_site'=>'https://www.jouvence.fr','photo_principale'=>'images/projets/mockup-jouvence.png','ordre'=>1],
  ['titre'=>'Refonte site Moustikit','slug'=>'refonte-moustikit','categorie'=>'ecommerce','client'=>'Moustikit','annee'=>2022,'description'=>'Refonte du site e-commerce PrestaShop avec nouvelles icônes personnalisées.','mission'=>'UX, design icônes, intégration PrestaShop.','outils'=>'["Illustrator","Photoshop","PrestaShop"]','url_site'=>'','photo_principale'=>'images/projets/mockup-moustikit.png','ordre'=>2],
  ['titre'=>'Refonte site Pirus Composites','slug'=>'refonte-pirus','categorie'=>'web','client'=>'Pirus Composites','annee'=>2022,'description'=>'Refonte du site vitrine pour une PME industrielle.','mission'=>'Identité visuelle, WordPress, rédaction de contenu.','outils'=>'["Figma","WordPress","Photoshop"]','url_site'=>'','photo_principale'=>'images/projets/mockup-pirus.png','ordre'=>3],
  ['titre'=>'Logo & site BlaBlaPattes','slug'=>'blablapattes','categorie'=>'logo','client'=>'BlaBlaPattes','annee'=>2021,'description'=>'Création complète de l\'identité visuelle et du site vitrine.','mission'=>'Logo, charte graphique, site vitrine WordPress.','outils'=>'["Illustrator","Figma","WordPress"]','url_site'=>'','photo_principale'=>'images/projets/mockup-blablapattes.png','ordre'=>4],
  ['titre'=>'Site Colorale Club','slug'=>'colorale','categorie'=>'web','client'=>'Colorale Club','annee'=>2020,'description'=>'Site web pour une association de véhicules vintage.','mission'=>'Design, WordPress, gestion des contenus.','outils'=>'["Photoshop","WordPress"]','url_site'=>'','photo_principale'=>'images/projets/mockup-colorale.png','ordre'=>5],
  ['titre'=>'Invitation Noces de Diamant','slug'=>'noces-diamant','categorie'=>'print','client'=>'Particulier','annee'=>2023,'description'=>'Création sur-mesure d\'une invitation pour des noces de diamant.','mission'=>'Conception graphique print, mise en page InDesign.','outils'=>'["Photoshop","InDesign"]','url_site'=>'','photo_principale'=>'images/projets/noces-diamant-invitation.png','ordre'=>6],
  ['titre'=>'Invitation anniversaire 50 ans moto','slug'=>'invitation-anniversaire','categorie'=>'print','client'=>'Particulier','annee'=>2022,'description'=>'Invitation personnalisée pour un anniversaire sur le thème de la moto.','mission'=>'Conception graphique, illustration, print.','outils'=>'["Photoshop","InDesign"]','url_site'=>'','photo_principale'=>'images/projets/invitationrenald.png','ordre'=>7],
  ['titre'=>'Invitation anniversaire 60 ans','slug'=>'invitation-60ans','categorie'=>'print','client'=>'Particulier','annee'=>2022,'description'=>'Invitation carte postale aérienne pour une passionnée de voyages en van.','mission'=>'Conception graphique, illustration, print.','outils'=>'["Photoshop","InDesign"]','url_site'=>'','photo_principale'=>'images/projets/invitation-retraite-cathy.png','ordre'=>8],
  ['titre'=>'World Cleanup Day','slug'=>'worldcleanupday','categorie'=>'print','client'=>'Association','annee'=>2021,'description'=>'Affiche et supports de communication pour l\'événement World Cleanup Day.','mission'=>'Conception affiche, supports digitaux.','outils'=>'["Photoshop","Illustrator"]','url_site'=>'','photo_principale'=>'images/projets/affiche-worldcleanupday.png','ordre'=>9],
];

$resultats = [];
foreach ($projets as $p) {
  try {
    $check = $pdo->prepare("SELECT id FROM projets WHERE slug = ?");
    $check->execute([$p['slug']]);
    if ($check->fetch()) {
      $resultats[] = ['exists', 'Déjà présent : ' . $p['titre']];
      continue;
    }
    $stmt = $pdo->prepare("INSERT INTO projets (titre,slug,categorie,client,annee,description,mission,outils,url_site,photo_principale,ordre,visible) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
    $stmt->execute([$p['titre'],$p['slug'],$p['categorie'],$p['client'],$p['annee'],$p['description'],$p['mission'],$p['outils'],$p['url_site'],$p['photo_principale'],$p['ordre']]);
    $resultats[] = ['ok', 'Importé : ' . $p['titre']];
  } catch(PDOException $e) {
    $resultats[] = ['err', 'Erreur ' . $p['titre'] . ' : ' . $e->getMessage()];
  }
}
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Import projets</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Inter',sans-serif;background:#FAF8FF;padding:2rem}
.card{background:#fff;border-radius:14px;padding:1.5rem;max-width:600px;border:.5px solid rgba(139,107,177,.18)}
h1{font-size:16px;font-weight:500;margin-bottom:1rem;color:#2D1B69}
.item{display:flex;align-items:flex-start;gap:8px;padding:.5rem .7rem;border-radius:8px;margin-bottom:6px;font-size:13px}
.ok{background:#EAF3DE;color:#27500A}.exists{background:#F0E6FF;color:#2D1B69}.err{background:#FCEBEB;color:#A32D2D}
.icon{flex-shrink:0;font-weight:600}
a{display:inline-block;margin-top:1rem;background:#2D1B69;color:#fff;padding:8px 18px;border-radius:20px;font-size:13px;text-decoration:none}</style></head>
<body><div class="card"><h1>📥 Import des projets existants</h1>
<?php foreach ($resultats as [$type,$msg]): ?>
<div class="item <?= $type ?>"><span class="icon"><?= $type==='ok'?'✓':($type==='exists'?'→':'✗') ?></span><span><?= htmlspecialchars($msg) ?></span></div>
<?php endforeach; ?>
<a href="/admin/projets.php">← Voir les projets</a></div></body></html>
