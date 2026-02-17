<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
  echo json_encode(['success' => false, 'message' => 'No autorizado']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

require_once __DIR__ . '/database.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
  echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
  exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode(['success' => false, 'message' => 'JSON inválido o vacío']);
  exit;
}

$tiendaId = isset($data['tiendaId']) ? (int)$data['tiendaId'] : 0;
$comentario = trim((string)($data['comentario'] ?? ''));

if ($tiendaId <= 0) {
  echo json_encode(['success' => false, 'message' => 'ID de tienda no válido']);
  exit;
}

// Si el usuario no escribió nada, no fallamos: simplemente no guardamos
if ($comentario === '') {
  echo json_encode(['success' => true, 'message' => 'Sin comentario, nada que guardar']);
  exit;
}

// (Opcional recomendado) validar que la tienda exista y sea del usuario
$chk = $conn->prepare("SELECT id FROM tiendas WHERE id = ? AND usuario_id = ? LIMIT 1");
if (!$chk) {
  echo json_encode(['success' => false, 'message' => 'Error preparando validación: ' . $conn->error]);
  exit;
}
$chk->bind_param("ii", $tiendaId, $usuario_id);
$chk->execute();
$chk->store_result();

if ($chk->num_rows === 0) {
  $chk->close();
  $conn->close();
  echo json_encode(['success' => false, 'message' => 'Tienda no encontrada o no pertenece al usuario']);
  exit;
}
$chk->close();

$stmt = $conn->prepare("INSERT INTO comentarios_visita (tienda_id, usuario_id, comentario) VALUES (?, ?, ?)");
if (!$stmt) {
  echo json_encode(['success' => false, 'message' => 'Error preparando insert: ' . $conn->error]);
  exit;
}

$stmt->bind_param("iis", $tiendaId, $usuario_id, $comentario);

if ($stmt->execute()) {
  echo json_encode(['success' => true, 'message' => 'Comentario guardado']);
} else {
  echo json_encode(['success' => false, 'message' => 'Error guardando comentario: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
