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

// ✅ Leer JSON (porque el JS manda application/json)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode(['success' => false, 'message' => 'JSON inválido o vacío']);
  exit;
}

$tiendaId = isset($data['tiendaId']) ? (int)$data['tiendaId'] : 0;
$productos = $data['productos'] ?? [];

if ($tiendaId <= 0) {
  echo json_encode(['success' => false, 'message' => 'ID de tienda no válido: ' . $tiendaId]);
  exit;
}

if (!is_array($productos) || count($productos) === 0) {
  echo json_encode(['success' => false, 'message' => 'No hay productos para guardar']);
  exit;
}

// (Opcional pero recomendado) validar que tienda exista y sea del usuario
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

/**
 * ✅ Tu tabla real:
 * productos_capturados
 * columnas: tienda_id, marca, bloom, presentacion, precio, usuario_id (fecha_captura se genera sola)
 */
$sql = "INSERT INTO `productos_capturados`
          (`tienda_id`, `marca`, `bloom`, `presentacion`, `precio`, `usuario_id`)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode(['success' => false, 'message' => 'Error preparando insert: ' . $conn->error]);
  exit;
}

$conn->begin_transaction();

try {
  foreach ($productos as $p) {
    $marca = trim((string)($p['marca'] ?? ''));
    $bloom = trim((string)($p['bloom'] ?? ''));
    $presentacion = trim((string)($p['presentacion'] ?? ''));
    $precio = isset($p['precio']) ? (float)$p['precio'] : 0;

    // Validación mínima (enums los valida la BD también)
    if ($marca === '' || $bloom === '' || $presentacion === '' || $precio <= 0) {
      throw new Exception('Producto inválido (marca/bloom/presentacion/precio)');
    }

    $stmt->bind_param("isssdi", $tiendaId, $marca, $bloom, $presentacion, $precio, $usuario_id);

    if (!$stmt->execute()) {
      throw new Exception('Error insertando producto: ' . $stmt->error);
    }
  }

  $conn->commit();
  $stmt->close();
  $conn->close();

  echo json_encode(['success' => true, 'message' => 'Productos guardados correctamente']);
  exit;

} catch (Exception $e) {
  $conn->rollback();
  $stmt->close();
  $conn->close();

  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
  exit;
}
