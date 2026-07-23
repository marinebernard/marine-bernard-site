<?php
/**
 * Reçoit le formulaire de contact (pages/contact-rando.html) en JSON
 * et enregistre le message dans la base MariaDB/MySQL. Crée la table
 * automatiquement au premier envoi — aucune manipulation phpMyAdmin
 * nécessaire au départ. Les messages reçus sont consultables ensuite
 * via phpMyAdmin (fourni par IONOS) sur la table contact_messages.
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

$name    = trim($input['name'] ?? '');
$email   = trim($input['email'] ?? '');
$subject = trim($input['subject'] ?? '');
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

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('contact-handler.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Erreur serveur, réessaie plus tard."]);
}
