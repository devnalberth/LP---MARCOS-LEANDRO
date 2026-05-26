<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config.php';

$id     = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
$status = in_array($_POST['status'] ?? '', ['new', 'read']) ? $_POST['status'] : 'read';

if (!$id) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE contacts SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao atualizar']);
}
