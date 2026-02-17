<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

/* =========================
   RUTAS SEGURAS (DREAMHOST)
========================= */
$tcpdfPath = __DIR__ . '/TCPDF/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    die('Error: No se encontró TCPDF en: ' . $tcpdfPath);
}
require_once $tcpdfPath;

require_once __DIR__ . '/database.php';
if (!isset($conn) || !$conn || $conn->connect_error) {
    die('Error: No se pudo conectar a la base de datos.');
}

/* =========================
   PARAMETROS (IGUAL QUE reportes.php)
========================= */
$reporte_tipo = $_GET['reporte'] ?? 'marca';

$marca_filtro = $_GET['marca'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin    = $_GET['fecha_fin'] ?? '';
$tienda_id    = $_GET['tienda_id'] ?? '';

$bloom_filtro        = $_GET['bloom'] ?? '';
$presentacion_filtro = $_GET['presentacion'] ?? '';

$usuario_filtro = $_GET['usuario_id'] ?? '';

/* =========================
   HELPERS
========================= */
function fmt_money($v) {
    if ($v === null || $v === '') return 'N/A';
    return '$' . number_format((float)$v, 2);
}
function fmt_date($v, $withTime = false) {
    if ($v === null || $v === '') return 'N/A';
    $ts = strtotime($v);
    if (!$ts) return 'N/A';
    return $withTime ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
}
function escape($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return $host ? ($scheme . '://' . $host) : '';
}
function photo_public_url($val) {
    if (empty($val)) return '';
    $v = trim($val);
    if (preg_match('#^https?://#i', $v)) return $v;
    $v = ltrim($v, '/');
    if (stripos($v, 'uploads/') === 0) return base_url() . '/' . $v;
    return base_url() . '/uploads/' . $v;
}

/* Ejecutar query preparada y regresar rows */
function run_prepared(mysqli $conn, string $sql, string $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error preparando SQL: " . $conn->error . "<br><pre>$sql</pre>");
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

/* =========================
   WHERE + PARAMS (igual que reportes.php)
========================= */
$where = " WHERE 1=1 ";
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
   DEFINIR REPORTE
========================= */
$page_title = "Reporte";
$extra_note = "";

$rows = [];
$columns = []; // key => label

// Para usuarios_detalle
$columns_detalle_productos = [
    'usuario' => 'Usuario',
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'precio' => 'Precio',
    'fecha' => 'Fecha'
];

$comentariosPorTienda = []; // tienda_id => lista

if ($reporte_tipo === 'marca') {
    $page_title = "Reporte: Precios Promedio por Marca";

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

    $rows = run_prepared($conn, $sql, $types, $params);

    $columns = [
        'marca' => 'Marca',
        'total_productos' => 'Total Registros',
        'precio_minimo' => 'Precio Mínimo',
        'precio_maximo' => 'Precio Máximo',
        'precio_promedio' => 'Precio Promedio',
        'ultima_captura' => 'Última Captura'
    ];
}

elseif ($reporte_tipo === 'top') {
    $page_title = "Reporte: Top Marcas (Más caras / Más baratas)";
    $extra_note = "Top 10 más caras y Top 10 más baratas (por precio promedio).";

    // Evitar UNION con placeholders duplicados: sacamos todo y en PHP hacemos TOPs
    $sqlAll = "SELECT 
                  p.marca,
                  AVG(p.precio) as precio_promedio,
                  COUNT(*) as total_registros
               FROM productos_capturados p
               $where
               GROUP BY p.marca";

    $all = run_prepared($conn, $sqlAll, $types, $params);

    // Ordenar
    $caras = $all;
    usort($caras, fn($a,$b) => ($b['precio_promedio'] <=> $a['precio_promedio']));
    $caras = array_slice($caras, 0, 10);

    $baratas = $all;
    usort($baratas, fn($a,$b) => ($a['precio_promedio'] <=> $b['precio_promedio']));
    $baratas = array_slice($baratas, 0, 10);

    // Guardamos en $rows como estructura separada
    $rows = [
        'caras' => $caras,
        'baratas' => $baratas
    ];

    // columnas para ambas tablas
    $columns = [
        'marca' => 'Marca',
        'precio_promedio' => 'Precio Promedio',
        'total_registros' => 'Total Registros'
    ];
}

elseif ($reporte_tipo === 'presentacion') {
    $page_title = "Reporte: Por Presentación + Bloom (historial)";
    $extra_note = "Agrupa por marca + bloom + presentación + fecha.";

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

    $rows = run_prepared($conn, $sql, $types, $params);

    $columns = [
        'marca' => 'Marca',
        'bloom' => 'Bloom',
        'presentacion' => 'Presentación',
        'fecha' => 'Fecha',
        'precio_minimo' => 'Precio Mínimo',
        'precio_maximo' => 'Precio Máximo',
        'precio_promedio' => 'Precio Promedio',
        'capturas' => 'Capturas'
    ];
}

elseif ($reporte_tipo === 'usuarios') {
    $page_title = "Reporte: Actividad de Usuarios (Resumen)";
    $extra_note = "Capturas por usuario en el periodo seleccionado.";

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

    $rows = run_prepared($conn, $sql, $types, $params);

    $columns = [
        'usuario' => 'Usuario',
        'total_capturas' => 'Total Capturas',
        'dias_activos' => 'Días Activos',
        'primera_captura' => 'Primera Captura',
        'ultima_captura' => 'Última Captura'
    ];
}

elseif ($reporte_tipo === 'usuarios_detalle') {
    $page_title = "Reporte: Actividad por Usuario (Desglosado)";
    $extra_note = "Primero muestra la tienda, debajo capturas. Encabezado incluye foto (link) y comentarios.";

    $sql = "SELECT
                p.tienda_id,
                u.nombre_completo as usuario,
                t.nombre_tienda as tienda,
                t.foto_estanteria as foto_estanteria,
                p.marca,
                p.bloom,
                p.presentacion,
                p.precio,
                DATE(p.fecha_captura) as fecha
            FROM productos_capturados p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN tiendas t ON p.tienda_id = t.id
            $where
            ORDER BY t.nombre_tienda ASC, p.fecha_captura DESC
            LIMIT 900";

    $rows = run_prepared($conn, $sql, $types, $params);

    $columns = $columns_detalle_productos;

    // Traer comentarios por tienda presentes
    if (!empty($rows)) {
        $tiendaIds = [];
        foreach ($rows as $r) {
            if (!empty($r['tienda_id'])) $tiendaIds[(int)$r['tienda_id']] = true;
        }
        $tiendaIds = array_keys($tiendaIds);

        if (!empty($tiendaIds)) {
            $in = implode(',', array_fill(0, count($tiendaIds), '?'));

            $sqlC = "SELECT
                        c.tienda_id,
                        c.comentario,
                        c.fecha_registro AS fecha,
                        u.nombre_completo AS usuario
                     FROM comentarios_visita c
                     LEFT JOIN usuarios u ON c.usuario_id = u.id
                     WHERE c.tienda_id IN ($in)";

            $paramsC = $tiendaIds;
            $typesC  = str_repeat('i', count($tiendaIds));

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

            $comRows = run_prepared($conn, $sqlC, $typesC, $paramsC);

            foreach ($comRows as $c) {
                $tid = (int)($c['tienda_id'] ?? 0);
                if (!$tid) continue;
                if (!isset($comentariosPorTienda[$tid])) $comentariosPorTienda[$tid] = [];
                $comentariosPorTienda[$tid][] = $c;
            }
        }
    }
}

elseif ($reporte_tipo === 'fuera_rango') {
    $page_title = "Reporte: Capturas Fuera de Rango (posibles errores)";
    $extra_note = "Precios fuera de (promedio ± 2*STDDEV) por marca.";

    $sql = "SELECT
                p.marca,
                p.bloom,
                p.presentacion,
                t.nombre_tienda as tienda,
                t.foto_estanteria as foto_estanteria,
                p.precio,
                DATE(p.fecha_captura) as fecha,
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
            ORDER BY fecha DESC
            LIMIT 600";

    $rows = run_prepared($conn, $sql, $types, $params);

    $columns = [
        'marca' => 'Marca',
        'bloom' => 'Bloom',
        'presentacion' => 'Presentación',
        'tienda' => 'Tienda',
        'precio' => 'Precio',
        'fecha' => 'Fecha',
        'promedio_marca' => 'Promedio Marca',
        'std_marca' => 'STD Marca',
        'foto_estanteria' => 'Foto Estantería'
    ];
}

else {
    $reporte_tipo = 'marca';
    $page_title = "Reporte: Precios Promedio por Marca";
}

/* =========================
   CONFIG TCPDF
========================= */
$pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Sistema Captura de Precios');
$pdf->SetAuthor('Progel');
$pdf->SetTitle($page_title);

$pdf->SetHeaderData('', 0, $page_title, 'Generado el: ' . date('d/m/Y H:i:s'));
$pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
$pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 18);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 10);

/* =========================
   CABECERA (siempre legible)
========================= */
$htmlHeader = '
<div style="background-color:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;">
  <div style="font-size:18px;font-weight:bold;margin-bottom:4px;">' . escape($page_title) . '</div>
  <div style="font-size:12px;color:#374151;">
    <b>Fecha:</b> ' . date('d/m/Y') . '
    ' . (!empty($extra_note) ? ('<br><b>Nota:</b> ' . escape($extra_note)) : '') . '
  </div>
</div>
<br>
';

$pdf->writeHTML($htmlHeader, true, false, true, false, '');

/* =========================
   RENDER TABLAS
   ✅ FIX: capturas/total_capturas NO se tratan como fecha
========================= */
function render_table($rows, $columns) {
    if (empty($rows)) return '<div style="padding:14px;border:1px solid #eee;border-radius:10px;">No hay datos.</div>';

    $thead = '<tr style="background-color:#111827;color:#ffffff;">';
    foreach ($columns as $k => $label) {
        $thead .= '<th style="padding:7px;">' . escape($label) . '</th>';
    }
    $thead .= '</tr>';

    $tbody = '';
    foreach ($rows as $row) {
        $tbody .= '<tr>';
        foreach ($columns as $k => $label) {
            $val = $row[$k] ?? null;

            // ✅ Conteos: NO son fechas
            if (in_array($k, ['capturas','total_capturas','dias_activos','total_productos','total_registros'], true)) {
                $cell = ($val === null || $val === '') ? '0' : (string)(int)$val;

            } elseif (str_contains($k, 'precio') || $k === 'promedio_marca') {
                $cell = fmt_money($val);

            } elseif (in_array($k, ['fecha','primera_captura','ultima_captura'], true)) {
                $cell = fmt_date($val);

            } elseif ($k === 'std_marca') {
                $cell = ($val === null || $val === '') ? 'N/A' : number_format((float)$val, 2);

            } elseif ($k === 'foto_estanteria') {
                $url = photo_public_url((string)$val);
                $cell = $url ? '<a href="'.escape($url).'">Abrir foto</a>' : 'Sin foto';

            } else {
                $cell = escape($val);
            }

            $tbody .= '<td style="padding:7px;border-bottom:1px solid #e5e7eb;">' . $cell . '</td>';
        }
        $tbody .= '</tr>';
    }

    return '
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <thead>' . $thead . '</thead>
      <tbody>' . $tbody . '</tbody>
    </table>
    ';
}

/* =========================
   SALIDA POR REPORTE
========================= */
if ($reporte_tipo === 'top') {
    $html = '<h2 style="margin:0 0 8px 0;">Top 10 más caras</h2>';
    $html .= render_table($rows['caras'] ?? [], $columns);

    $html .= '<br><h2 style="margin:0 0 8px 0;">Top 10 más baratas</h2>';
    $html .= render_table($rows['baratas'] ?? [], $columns);

    $pdf->writeHTML($html, true, false, true, false, '');

} elseif ($reporte_tipo === 'usuarios_detalle') {

    if (empty($rows)) {
        $pdf->writeHTML('<div>No hay datos.</div>', true, false, true, false, '');
    } else {

        // Agrupar por tienda_id
        $grupos = [];
        foreach ($rows as $r) {
            $tid = (int)($r['tienda_id'] ?? 0);
            $nombre = $r['tienda'] ?? 'Sin tienda';
            if (!isset($grupos[$tid])) {
                $grupos[$tid] = [
                    'nombre' => $nombre,
                    'foto' => $r['foto_estanteria'] ?? '',
                    'rows' => []
                ];
            }
            $grupos[$tid]['rows'][] = $r;
        }

        foreach ($grupos as $tid => $g) {
            $fotoUrl = photo_public_url((string)($g['foto'] ?? ''));
            $comentarios = $comentariosPorTienda[$tid] ?? [];

            $html = '
            <div style="background:#f3f6fb;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;">
              <div style="font-size:14px;font-weight:bold;">🏪 ' . escape($g['nombre']) . '</div>
              <div style="font-size:11px;color:#374151;margin-top:4px;">
                <b>Foto:</b> ' . ($fotoUrl ? '<a href="'.escape($fotoUrl).'">Abrir foto</a>' : 'Sin foto') . '
                &nbsp; | &nbsp; <b>Registros:</b> ' . (int)count($g['rows']) . '
              </div>';

            $html .= '<div style="margin-top:8px;font-size:11px;"><b>Comentarios:</b><br>';
            if (!empty($comentarios)) {
                $max = 3;
                $i = 0;
                $html .= '<ul style="margin:6px 0 0 16px;padding:0;">';
                foreach ($comentarios as $c) {
                    $i++; if ($i > $max) break;
                    $u = $c['usuario'] ?? '—';
                    $txt = trim((string)($c['comentario'] ?? ''));
                    $f = $c['fecha'] ?? '';
                    $html .= '<li style="margin:3px 0;">'
                        . '<b>' . escape($u) . ':</b> ' . escape($txt)
                        . ($f ? ' <span style="color:#6b7280;">(' . escape(fmt_date($f, true)) . ')</span>' : '')
                        . '</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<span style="color:#6b7280;">Sin comentarios</span>';
            }
            $html .= '</div></div><br>';

            // Tabla de productos
            $html .= render_table($g['rows'], $columns_detalle_productos);
            $html .= '<br><br>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->AddPage();
        }

        // Quitar página final vacía (si quedó)
        $pageCount = $pdf->getNumPages();
        $pdf->deletePage($pageCount);
    }

} else {
    // Reportes normales (una sola tabla)
    $html = render_table($rows, $columns);
    $pdf->writeHTML($html, true, false, true, false, '');
}

/* =========================
   OUTPUT
========================= */
$filename = 'reporte_' . preg_replace('/[^a-z0-9_]+/i', '_', $reporte_tipo) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'D');

$conn->close();
exit;
