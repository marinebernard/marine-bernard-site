<?php
/**
 * Reçoit le formulaire de contact (pages/contact-rando.html) en JSON,
 * enregistre le message dans la base MariaDB/MySQL (table créée
 * automatiquement au premier envoi) puis envoie une notification à
 * contact@marine-bernard.fr. L'enregistrement en base reste la
 * référence : si l'email échoue, le message n'est pas perdu pour
 * autant, il reste consultable via phpMyAdmin.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration serveur manquante.']);
    exit;
}
require $configPath;

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// str_replace retire les retours à la ligne : évite l'injection d'en-têtes
// email via un champ "sujet" ou "nom" malveillant.
$name    = str_replace(["\r", "\n"], '', trim($input['name'] ?? ''));
$email   = str_replace(["\r", "\n"], '', trim($input['email'] ?? ''));
$subject = str_replace(["\r", "\n"], '', trim($input['subject'] ?? ''));
$message = trim($input['message'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Merci de remplir les champs obligatoires.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Adresse email invalide.']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        subject VARCHAR(255) DEFAULT '',
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $subject, $message]);
} catch (PDOException $e) {
    error_log('contact-handler.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Erreur serveur, réessaie plus tard."]);
    exit;
}

// Le message est déjà en sécurité en base à ce stade — l'email n'est
// qu'une notification "best effort", son échec ne fait pas échouer la requête.
$mailSubject = $subject !== '' ? $subject : 'Nouveau message depuis marine-bernard.fr';
$mailBody    = "Nouveau message via le formulaire de contact rando :\n\n"
             . "Nom : {$name}\n"
             . "Email : {$email}\n\n"
             . "{$message}\n";
$mailHeaders = "From: Site marine-bernard.fr <contact@marine-bernard.fr>\r\n"
             . "Reply-To: {$name} <{$email}>\r\n"
             . "Content-Type: text/plain; charset=utf-8";

@mail('contact@marine-bernard.fr', $mailSubject, $mailBody, $mailHeaders);

echo json_encode(['success' => true]);
