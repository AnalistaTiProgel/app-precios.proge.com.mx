<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/database.php';

/* =========================
   PARAMETROS
========================= */
$reporte_tipo = $_GET['reporte'] ?? 'marca';
// marca | top | presentacion | usuarios | usuarios_detalle | fuera_rango

$marca_filtro        = $_GET['marca'] ?? '';
$fecha_inicio        = $_GET['fecha_inicio'] ?? '';
$fecha_fin           = $_GET['fecha_fin'] ?? '';
$tienda_id           = $_GET['tienda_id'] ?? '';

$bloom_filtro        = $_GET['bloom'] ?? '';
$presentacion_filtro = $_GET['presentacion'] ?? '';

$usuario_filtro      = $_GET['usuario_id'] ?? '';

/* =========================
   CSV HEADERS
========================= */
$filename = 'export_' . preg_replace('/[^a-z0-9_\-]/i', '_', (string)$reporte_tipo) . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$out = fopen('php://output', 'w');
// BOM para Excel (acentos bien)
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

/* =========================
   HELPERS
========================= */
function csv_money($v): string {
    if ($v === null || $v === '') return '';
    // Formato numérico "usable"
    return number_format((float)$v, 2, '.', '');
}

function csv_date($v): string {
    if (empty($v)) return '';
    $ts = strtotime((string)$v);
    if (!$ts) return '';
    return date('Y-m-d', $ts);
}

function csv_datetime($v): string {
    if (empty($v)) return '';
    $ts = strtotime((string)$v);
    if (!$ts) return '';
    return date('Y-m-d H:i:s', $ts);
}

function photo_public_url_csv($val): string {
    if (empty($val)) return '';
    $v = trim((string)$val);
    if ($v === '') return '';

    if (preg_match('#^https?://#i', $v)) return $v;

    $v = ltrim($v, '/');

    if (stripos($v, 'uploads/') === 0) {
        return '/' . $v;
    }
    return '/uploads/' . $v;
}

/* =========================
   BASE WHERE
========================= */
$where  = " WHERE 1=1 ";
$params = [];
$types  = '';

if (!empty($marca_filtro)) {
    $where .= " AND p.marca = ? ";
    $params[] = $marca_filtro;
    $types .= "s";
}
if (!empty($bloom_filtro)) {
    $where .= " AND p.bloom = ? ";
    $params[] = $bloom_filtro;
    $types .= "s";
}
if (!empty($presentacion_filtro)) {
    $where .= " AND p.presentacion = ? ";
    $params[] = $presentacion_filtro;
    $types .= "s";
}
if (!empty($fecha_inicio)) {
    $where .= " AND DATE(p.fecha_captura) >= ? ";
    $params[] = $fecha_inicio;
    $types .= "s";
}
if (!empty($fecha_fin)) {
    $where .= " AND DATE(p.fecha_captura) <= ? ";
    $params[] = $fecha_fin;
    $types .= "s";
}
if (!empty($tienda_id)) {
    $where .= " AND p.tienda_id = ? ";
    $params[] = (int)$tienda_id;
    $types .= "i";
}
if (!empty($usuario_filtro)) {
    $where .= " AND p.usuario_id = ? ";
    $params[] = (int)$usuario_filtro;
    $types .= "i";
}

/* =========================
   SQL + COLUMNAS POR REPORTE
========================= */
$sql = '';
$headers = [];   // columnas del CSV
$rows = [];      // filas finales (arrays)

if ($reporte_tipo === 'marca') {

    $sql = "SELECT
                p.marca,
                COUNT(*) as total_productos,
                MIN(p.precio) as precio_minimo,
                MAX(p.precio) as precio_maximo,
                AVG(p.precio) as precio_promedio,
                MAX(DATE(p.fecha_captura)) as ultima_captura
            FROM productos_capturados p
            $where
            GROUP BY p.marca
            ORDER BY precio_promedio DESC";

    $headers = ['Marca','Total Registros','Precio Minimo','Precio Maximo','Precio Promedio','Ultima Captura'];

} elseif ($reporte_tipo === 'top') {

    $sql = "SELECT * FROM (
                SELECT
                    'Top Caras' as grupo,
                    p.marca,
                    AVG(p.precio) as precio_promedio,
                    COUNT(*) as total_registros
                FROM productos_capturados p
                $where
                GROUP BY p.marca
                ORDER BY precio_promedio DESC
                LIMIT 10
            ) A
            UNION ALL
            SELECT * FROM (
                SELECT
                    'Top Baratas' as grupo,
                    p.marca,
                    AVG(p.precio) as precio_promedio,
                    COUNT(*) as total_registros
                FROM productos_capturados p
                $where
                GROUP BY p.marca
                ORDER BY precio_promedio ASC
                LIMIT 10
            ) B";

    $headers = ['Grupo','Marca','Precio Promedio','Total Registros'];

} elseif ($reporte_tipo === 'presentacion') {

    $sql = "SELECT
                p.marca,
                p.bloom,
                p.presentacion,
                DATE(p.fecha_captura) as fecha,
                MIN(p.precio) as precio_minimo,
                MAX(p.precio) as precio_maximo,
                AVG(p.precio) as precio_promedio,
                COUNT(*) as capturas
            FROM productos_capturados p
            $where
            GROUP BY p.marca, p.bloom, p.presentacion, DATE(p.fecha_captura)
            ORDER BY fecha DESC, p.marca ASC
            LIMIT 800";

    $headers = ['Marca','Bloom','Presentacion','Fecha','Precio Minimo','Precio Maximo','Precio Promedio','Capturas'];

} elseif ($reporte_tipo === 'usuarios') {

    $sql = "SELECT
                u.nombre_completo as usuario,
                COUNT(*) as total_capturas,
                COUNT(DISTINCT DATE(p.fecha_captura)) as dias_activos,
                MIN(DATE(p.fecha_captura)) as primera_captura,
                MAX(DATE(p.fecha_captura)) as ultima_captura
            FROM productos_capturados p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            $where
            GROUP BY u.nombre_completo
            ORDER BY total_capturas DESC";

    $headers = ['Usuario','Total Capturas','Dias Activos','Primera Captura','Ultima Captura'];

} elseif ($reporte_tipo === 'usuarios_detalle') {

    // Aquí incluimos tienda_id para luego traer comentarios
    $sql = "SELECT
                p.tienda_id,
                t.nombre_tienda as tienda,
                t.foto_estanteria as foto_estanteria,
                u.nombre_completo as usuario,
                p.marca,
                p.bloom,
                p.presentacion,
                p.precio,
                p.fecha_captura
            FROM productos_capturados p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN tiendas t ON p.tienda_id = t.id
            $where
            ORDER BY t.nombre_tienda ASC, p.fecha_captura DESC
            LIMIT 1500";

    // CSV “plano” para análisis:
    // - Repetimos Tienda y Foto en cada fila
    // - Incluimos comentarios concatenados en una columna (últimos 3)
    $headers = [
        'Tienda',
        'Foto Estanteria URL',
        'Comentarios (ultimos 3)',
        'Usuario',
        'Marca',
        'Bloom',
        'Presentacion',
        'Precio',
        'Fecha Captura'
    ];

} elseif ($reporte_tipo === 'fuera_rango') {

    $sql = "SELECT
                p.marca,
                p.bloom,
                p.presentacion,
                t.nombre_tienda as tienda,
                t.foto_estanteria as foto_estanteria,
                p.precio,
                p.fecha_captura,
                stats.promedio_marca,
                stats.std_marca
            FROM productos_capturados p
            LEFT JOIN tiendas t ON p.tienda_id = t.id
            JOIN (
                SELECT
                    marca,
                    AVG(precio) as promedio_marca,
                    STDDEV_SAMP(precio) as std_marca
                FROM productos_capturados
                GROUP BY marca
            ) stats ON p.marca = stats.marca
            $where
            AND stats.std_marca IS NOT NULL
            AND (p.precio > (stats.promedio_marca + 2*stats.std_marca)
                 OR p.precio < (stats.promedio_marca - 2*stats.std_marca))
            ORDER BY p.fecha_captura DESC
            LIMIT 1200";

    $headers = [
        'Marca','Bloom','Presentacion','Tienda','Foto Estanteria URL',
        'Precio','Fecha Captura','Promedio Marca','STD Marca'
    ];

} else {
    // default
    $reporte_tipo = 'marca';
    $sql = "SELECT
                p.marca,
                COUNT(*) as total_productos,
                MIN(p.precio) as precio_minimo,
                MAX(p.precio) as precio_maximo,
                AVG(p.precio) as precio_promedio,
                MAX(DATE(p.fecha_captura)) as ultima_captura
            FROM productos_capturados p
            $where
            GROUP BY p.marca
            ORDER BY precio_promedio DESC";
    $headers = ['Marca','Total Registros','Precio Minimo','Precio Maximo','Precio Promedio','Ultima Captura'];
}

/* =========================
   EJECUTAR SQL PRINCIPAL
========================= */
$stmt = $conn->prepare($sql);
if (!$stmt) {
    // No imprimimos HTML, pero sí devolvemos algo entendible en CSV
    fputcsv($out, ['ERROR', 'SQL prepare failed', $conn->error]);
    fclose($out);
    $conn->close();
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

/* =========================
   WRITE HEADERS
========================= */
fputcsv($out, $headers);

/* =========================
   REPORTE usuarios_detalle: traer comentarios por tienda
========================= */
$comentariosPorTienda = []; // tienda_id => "coment1 | coment2 | coment3"

if ($reporte_tipo === 'usuarios_detalle') {
    // juntar tienda_ids
    $tiendaIds = [];
    $tmpRows = [];
    while ($r = $res->fetch_assoc()) {
        $tmpRows[] = $r;
        if (!empty($r['tienda_id'])) $tiendaIds[(int)$r['tienda_id']] = true;
    }
    $tiendaIds = array_keys($tiendaIds);

    if (!empty($tiendaIds)) {
        $in = implode(',', array_fill(0, count($tiendaIds), '?'));

        $sqlC = "SELECT
                    c.tienda_id,
                    c.comentario,
                    c.fecha_registro as fecha,
                    u.nombre_completo as usuario
                 FROM comentarios_visita c
                 LEFT JOIN usuarios u ON c.usuario_id = u.id
                 WHERE c.tienda_id IN ($in)";

        $paramsC = $tiendaIds;
        $typesC  = str_repeat('i', count($tiendaIds));

        // filtros opcionales por fecha/usuario (si aplican)
        if (!empty($fecha_inicio)) {
            $sqlC .= " AND DATE(c.fecha_registro) >= ? ";
            $paramsC[] = $fecha_inicio;
            $typesC .= "s";
        }
        if (!empty($fecha_fin)) {
            $sqlC .= " AND DATE(c.fecha_registro) <= ? ";
            $paramsC[] = $fecha_fin;
            $typesC .= "s";
        }
        if (!empty($usuario_filtro)) {
            $sqlC .= " AND c.usuario_id = ? ";
            $paramsC[] = (int)$usuario_filtro;
            $typesC .= "i";
        }

        $sqlC .= " ORDER BY c.fecha_registro DESC";

        $stmtC = $conn->prepare($sqlC);
        if ($stmtC) {
            $stmtC->bind_param($typesC, ...$paramsC);
            $stmtC->execute();
            $resC = $stmtC->get_result();

            // guardar top 3 por tienda
            $countPer = [];
            while ($c = $resC->fetch_assoc()) {
                $tid = (int)($c['tienda_id'] ?? 0);
                if (!$tid) continue;

                $countPer[$tid] = $countPer[$tid] ?? 0;
                if ($countPer[$tid] >= 3) continue; // top 3

                $txt = trim((string)($c['comentario'] ?? ''));
                if ($txt === '') continue;

                $u = trim((string)($c['usuario'] ?? ''));
                $f = csv_datetime($c['fecha'] ?? '');

                $line = $u !== '' ? ($u . ': ' . $txt) : $txt;
                if ($f !== '') $line .= ' (' . $f . ')';

                if (!isset($comentariosPorTienda[$tid])) $comentariosPorTienda[$tid] = [];
                $comentariosPorTienda[$tid][] = $line;

                $countPer[$tid]++;
            }

            // convertir a string
            foreach ($comentariosPorTienda as $tid => $arr) {
                $comentariosPorTienda[$tid] = implode(' | ', $arr);
            }

            $stmtC->close();
        }
    }

    // escribir filas
    foreach ($tmpRows as $row) {
        $tid = (int)($row['tienda_id'] ?? 0);
        $fotoUrl = photo_public_url_csv($row['foto_estanteria'] ?? '');
        $coment = $tid && isset($comentariosPorTienda[$tid]) ? $comentariosPorTienda[$tid] : '';

        fputcsv($out, [
            (string)($row['tienda'] ?? ''),
            (string)$fotoUrl,
            (string)$coment,
            (string)($row['usuario'] ?? ''),
            (string)($row['marca'] ?? ''),
            (string)($row['bloom'] ?? ''),
            (string)($row['presentacion'] ?? ''),
            csv_money($row['precio'] ?? null),
            csv_datetime($row['fecha_captura'] ?? '')
        ]);
    }

    fclose($out);
    $stmt->close();
    $conn->close();
    exit;
}

/* =========================
   RESTO DE REPORTES (write rows)
========================= */
while ($row = $res->fetch_assoc()) {

    if ($reporte_tipo === 'marca') {
        fputcsv($out, [
            $row['marca'] ?? '',
            (string)($row['total_productos'] ?? ''),
            csv_money($row['precio_minimo'] ?? null),
            csv_money($row['precio_maximo'] ?? null),
            csv_money($row['precio_promedio'] ?? null),
            csv_date($row['ultima_captura'] ?? '')
        ]);

    } elseif ($reporte_tipo === 'top') {
        fputcsv($out, [
            $row['grupo'] ?? '',
            $row['marca'] ?? '',
            csv_money($row['precio_promedio'] ?? null),
            (string)($row['total_registros'] ?? '')
        ]);

    } elseif ($reporte_tipo === 'presentacion') {
        fputcsv($out, [
            $row['marca'] ?? '',
            (string)($row['bloom'] ?? ''),
            (string)($row['presentacion'] ?? ''),
            csv_date($row['fecha'] ?? ''),
            csv_money($row['precio_minimo'] ?? null),
            csv_money($row['precio_maximo'] ?? null),
            csv_money($row['precio_promedio'] ?? null),
            (string)($row['capturas'] ?? '')
        ]);

    } elseif ($reporte_tipo === 'usuarios') {
        fputcsv($out, [
            (string)($row['usuario'] ?? ''),
            (string)($row['total_capturas'] ?? ''),
            (string)($row['dias_activos'] ?? ''),
            csv_date($row['primera_captura'] ?? ''),
            csv_date($row['ultima_captura'] ?? '')
        ]);

    } elseif ($reporte_tipo === 'fuera_rango') {
        $fotoUrl = photo_public_url_csv($row['foto_estanteria'] ?? '');
        fputcsv($out, [
            (string)($row['marca'] ?? ''),
            (string)($row['bloom'] ?? ''),
            (string)($row['presentacion'] ?? ''),
            (string)($row['tienda'] ?? ''),
            (string)$fotoUrl,
            csv_money($row['precio'] ?? null),
            csv_datetime($row['fecha_captura'] ?? ''),
            csv_money($row['promedio_marca'] ?? null),
            ($row['std_marca'] === null || $row['std_marca'] === '') ? '' : number_format((float)$row['std_marca'], 2, '.', '')
        ]);
    }
}

fclose($out);
$stmt->close();
$conn->close();
exit;
