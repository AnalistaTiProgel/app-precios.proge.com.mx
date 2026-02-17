<?php
// guardar_tienda.php  (MISMO DIRECTORIO que index.php, principal.php, etc.)
session_start();
header('Content-Type: application/json; charset=utf-8');

// (Opcional) en producción apaga errores HTML
// ini_set('display_errors', 0);
// error_reporting(E_ALL);

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// ✅ Conexión (database.php está en el MISMO DIRECTORIO)
require_once __DIR__ . '/database.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

/* =========================
   HELPERS IMAGEN
========================= */
function ensure_dir($dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Exception('No se pudo crear la carpeta uploads');
        }
    }
}

/**
 * Crea un recurso GD desde archivo según MIME
 */
function gd_from_file($tmpPath, $mime) {
    switch ($mime) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($tmpPath);
        case 'image/png':
            return @imagecreatefrompng($tmpPath);
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($tmpPath);
            return null;
        default:
            return null;
    }
}

/**
 * Guarda imagen optimizada (reescala + comprime).
 * Devuelve: ['filename' => 'uploads/xxx.webp', 'fs_path' => '/abs/path/uploads/xxx.webp']
 */
function save_optimized_image($tmpPath, $uploads_dir_fs, $uploads_dir_db, $baseNameNoExt) {
    $info = @getimagesize($tmpPath);
    if (!$info) throw new Exception('Archivo no es una imagen válida.');

    $mime = $info['mime'] ?? '';
    $w = (int)($info[0] ?? 0);
    $h = (int)($info[1] ?? 0);

    if ($w <= 0 || $h <= 0) throw new Exception('No se pudieron leer dimensiones de la imagen.');

    $src = gd_from_file($tmpPath, $mime);
    if (!$src) {
        throw new Exception('Formato no soportado o GD no puede leer esta imagen (' . $mime . ').');
    }

    // ✅ Reescalado: máximo 1280px en el lado más grande
    $maxSide = 1280;
    $scale = min($maxSide / max($w, 1), $maxSide / max($h, 1), 1); // nunca agrandar
    $newW = (int)round($w * $scale);
    $newH = (int)round($h * $scale);

    $dst = imagecreatetruecolor($newW, $newH);

    // Fondo blanco para PNG con alpha (evita transparencias raras)
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);

    // Reescalar
    if (!imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h)) {
        imagedestroy($src);
        imagedestroy($dst);
        throw new Exception('No se pudo procesar (reescalar) la imagen.');
    }

    // ✅ Guardar: WebP si existe, si no JPG
    $qualityWebp = 78; // 70–82 buen rango
    $qualityJpg  = 82;

    $outExt = function_exists('imagewebp') ? 'webp' : 'jpg';
    $filename = $baseNameNoExt . '.' . $outExt;

    $destino_fs = rtrim($uploads_dir_fs, '/\\') . DIRECTORY_SEPARATOR . $filename;
    $destino_db = rtrim($uploads_dir_db, '/\\') . '/' . $filename;

    $ok = false;
    if ($outExt === 'webp') {
        $ok = @imagewebp($dst, $destino_fs, $qualityWebp);
    } else {
        // JPG
        $ok = @imagejpeg($dst, $destino_fs, $qualityJpg);
    }

    imagedestroy($src);
    imagedestroy($dst);

    if (!$ok) {
        throw new Exception('No se pudo guardar la imagen optimizada en el servidor.');
    }

    return [
        'filename' => $destino_db,
        'fs_path'  => $destino_fs
    ];
}

// =======================
// 1) Leer inputs
// =======================
$nombreTienda      = trim($_POST['nombreTienda'] ?? '');
$direccion_manual  = trim($_POST['direccion_manual'] ?? '');
$direccion_gps     = trim($_POST['direccion'] ?? ''); // input readonly GPS
$latitud           = (isset($_POST['latitud'])  && $_POST['latitud']  !== '') ? (float)$_POST['latitud']  : null;
$longitud          = (isset($_POST['longitud']) && $_POST['longitud'] !== '') ? (float)$_POST['longitud'] : null;

// =======================
// 2) Validaciones obligatorias
// =======================
if ($nombreTienda === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre de la tienda es requerido']);
    exit;
}
if ($direccion_manual === '') {
    echo json_encode(['success' => false, 'message' => 'La dirección manual es requerida']);
    exit;
}
if ($latitud === null || $longitud === null) {
    echo json_encode(['success' => false, 'message' => 'La ubicación (latitud/longitud) es requerida']);
    exit;
}

if (!isset($_FILES['fotoEstanteria'])) {
    echo json_encode(['success' => false, 'message' => 'La foto de la estantería es requerida']);
    exit;
}

if ($_FILES['fotoEstanteria']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al subir la foto (código: ' . $_FILES['fotoEstanteria']['error'] . ')']);
    exit;
}

// =======================
// 3) Validar foto (peso y mime real)
// =======================
$tmpPath = $_FILES['fotoEstanteria']['tmp_name'] ?? '';

if (!$tmpPath || !is_uploaded_file($tmpPath)) {
    echo json_encode(['success' => false, 'message' => 'Archivo temporal inválido']);
    exit;
}

// Límite opcional (10MB)
$maxBytes = 10 * 1024 * 1024;
if (!empty($_FILES['fotoEstanteria']['size']) && $_FILES['fotoEstanteria']['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'La foto excede el tamaño máximo (10MB)']);
    exit;
}

// Validar tipo real (no solo extensión)
$info = @getimagesize($tmpPath);
$mime = $info['mime'] ?? '';
$permitidosMime = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mime, $permitidosMime, true)) {
    echo json_encode(['success' => false, 'message' => 'Formato de imagen no permitido (usa JPG/PNG/WEBP). Detectado: ' . $mime]);
    exit;
}

// =======================
// 4) Guardar imagen OPTIMIZADA en /uploads
// =======================
$uploads_dir_fs = __DIR__ . '/uploads';   // en disco
$uploads_dir_db = 'uploads';              // ruta relativa para BD

try {
    ensure_dir($uploads_dir_fs);

    // nombre base único
    $base = 'estanteria_' . time() . '_' . $usuario_id . '_' . bin2hex(random_bytes(4));

    $saved = save_optimized_image($tmpPath, $uploads_dir_fs, $uploads_dir_db, $base);

    $destino_db = $saved['filename']; // lo que se guarda en BD (ej: uploads/xxx.webp)
    $destino_fs = $saved['fs_path'];  // para borrar si falla BD

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// =======================
// 5) Insert en BD (ALINEADO A TU TABLA)
// columnas: nombre_tienda, direccion_manual, direccion_gps, latitud, longitud, foto_estanteria, usuario_id
// =======================
$sql = "INSERT INTO tiendas
        (nombre_tienda, direccion_manual, direccion_gps, latitud, longitud, foto_estanteria, usuario_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    @unlink($destino_fs);
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $conn->error]);
    exit;
}

// Tipos: s s s d d s i
$stmt->bind_param(
    "sssddsi",
    $nombreTienda,
    $direccion_manual,
    $direccion_gps,
    $latitud,
    $longitud,
    $destino_db,
    $usuario_id
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Tienda guardada correctamente',
        'tiendaId' => $stmt->insert_id
    ]);
} else {
    @unlink($destino_fs);
    echo json_encode(['success' => false, 'message' => 'Error ejecutando consulta: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
exit;
