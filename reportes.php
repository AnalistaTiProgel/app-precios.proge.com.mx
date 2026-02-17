<?php
session_start();


require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database.php';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/filtros.php';
require_once __DIR__ . '/includes/comentarios.php';
require_once __DIR__ . '/includes/catalogos.php';

$usuario_nombre = $_SESSION['usuario_nombre'] ?? '';
$reporte_tipo = $_GET['reporte'] ?? 'marca';

$map = require __DIR__ . '/reportes/reportes_map.php';
if (!isset($map[$reporte_tipo])) $reporte_tipo = 'marca';

// filtros -> where
$built = buildWhereFromFilters($_GET);
$where  = $built['where'];
$params = $built['params'];
$types  = $built['types'];
$filters = $built['filters'];

// cargar config del reporte
$reportConfig = require $map[$reporte_tipo];

$page_title = $reportConfig['page_title'] ?? 'Reportes de Precios';
$extra_note = $reportConfig['extra_note'] ?? '';
$sql        = $reportConfig['sql'] ?? '';
$columns    = $reportConfig['columns'] ?? [];
$columns_detalle_productos = $reportConfig['columns_detalle_productos'] ?? [];
$needs_comments = !empty($reportConfig['needs_comments']);

// ejecutar query principal
$stmt = $conn->prepare($sql);
if (!$stmt) die("Error preparando SQL: " . $conn->error . "<br><pre>$sql</pre>");

if (!empty($params)) $stmt->bind_param($types, ...$params);

$stmt->execute();
$res = $stmt->get_result();

$reportes = [];
while ($res && ($row = $res->fetch_assoc())) $reportes[] = $row;
$stmt->close();

// comentarios por tienda (solo usuarios_detalle)
$comentariosPorTienda = [];
if ($needs_comments && $reporte_tipo === 'usuarios_detalle' && !empty($reportes)) {
    $tiendaIds = [];
    foreach ($reportes as $r) {
        if (!empty($r['tienda_id'])) $tiendaIds[(int)$r['tienda_id']] = true;
    }
    $tiendaIds = array_keys($tiendaIds);
    $comentariosPorTienda = fetchComentariosPorTienda($conn, $tiendaIds, $filters);
}

// catálogos
$cat = loadCatalogos($conn);
$marcas = $cat['marcas'];
$tiendas = $cat['tiendas'];
$blooms = $cat['blooms'];
$presentaciones = $cat['presentaciones'];
$usuarios = $cat['usuarios'];

$conn->close();

// helpers de render
$self = htmlspecialchars($_SERVER['PHP_SELF']);

// ✅ fix para formateo (capturas NO es fecha)
$dateKeys  = ['fecha','primera_captura','ultima_captura'];
$moneyKeys = ['precio_minimo','precio_maximo','precio_promedio','precio','promedio_marca'];
$intKeys   = ['capturas','total_productos','total_registros','total_capturas','dias_activos'];

// render vista
require __DIR__ . '/views/reportes_view.php';
