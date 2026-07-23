<?php
/**
 * Copie ce fichier en "config.php" (même dossier) et remplis les vraies
 * valeurs données par IONOS lors de la création de la base MariaDB.
 *
 * IMPORTANT : "config.php" ne doit JAMAIS être commité dans Git (il est
 * déjà listé dans .gitignore). Upload-le à la main sur le serveur via
 * FTP/SFTP, dans le même dossier api/ que ce fichier.
 */

define('DB_HOST', 'exemple-hote.hosting-data.io');
define('DB_NAME', 'nom_de_ta_base');
define('DB_USER', 'ton_utilisateur');
define('DB_PASS', 'ton_mot_de_passe');
