<?php
/**
 * Copie ce fichier en "config.php" (même dossier) et remplace la valeur
 * ci-dessous par ton vrai mot de passe admin.
 *
 * IMPORTANT : "config.php" ne doit JAMAIS être commité dans Git (il est
 * déjà listé dans .gitignore, même logique que api/config.php). Upload-le
 * à la main sur le serveur via FTP/SFTP, dans ce même dossier admin/.
 *
 * Le mot de passe en clair ne reste que dans ce fichier local — auth.php
 * le hash au chargement (password_hash), aucun mot de passe en clair
 * n'est stocké en base ni commité dans le dépôt.
 */

define('ADMIN_PASSWORD', 'change-moi');
