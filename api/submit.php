<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config.php';

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

$source  = isset($_POST['_source']) ? sanitize($_POST['_source']) : 'appointment';
$name    = sanitize($_POST['name']    ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$service = sanitize($_POST['service']  ?? '');
$date    = sanitize($_POST['date']     ?? '');
$time    = sanitize($_POST['time']     ?? '');
$message = sanitize($_POST['message']  ?? '');

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Dados inválidos']);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT NOT NULL,
        email      TEXT NOT NULL,
        service    TEXT,
        date       TEXT,
        time       TEXT,
        message    TEXT,
        source     TEXT DEFAULT 'appointment',
        status     TEXT DEFAULT 'new',
        created_at TEXT DEFAULT (datetime('now','localtime'))
    )");

    $stmt = $pdo->prepare(
        "INSERT INTO contacts (name, email, service, date, time, message, source)
         VALUES (:name, :email, :service, :date, :time, :message, :source)"
    );
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':service' => $service,
        ':date'    => $date,
        ':time'    => $time,
        ':message' => $message,
        ':source'  => $source,
    ]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao salvar']);
}
