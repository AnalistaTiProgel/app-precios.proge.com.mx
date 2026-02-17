<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/includes/auth.php';

$usuario_id = (int)$_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'] ?? '';

require_once __DIR__ . '/database.php';

require_once __DIR__ . '/includes/principal_metrics.php';
require_once __DIR__ . '/includes/principal_calendar.php';

/* ====== METAS ====== */
$meta_dia = 3;
$meta_semana = 15;
$weeklyTarget = 15;

// columna fecha en tiendas
$fecha_col = 'fecha_registro';

// rango del mes
$monthStart = date('Y-m-01');
$monthEndExclusive = date('Y-m-01', strtotime('+1 month'));

// semana iso actual
$currentYear = (int)date('o');
$currentWeek = (int)date('W');
$currentYearWeekIso = (int)($currentYear . str_pad((string)$currentWeek, 2, '0', STR_PAD_LEFT));

// métricas
$tiendas_hoy = 0;
$tiendas_semana = 0;
$semanas_mes = [];

try {
    $m = getPrincipalMetrics($conn, $usuario_id, $fecha_col, $monthStart, $monthEndExclusive);
    $tiendas_hoy = (int)$m['tiendas_hoy'];
    $tiendas_semana = (int)$m['tiendas_semana'];
    $semanas_mes = $m['semanas_mes'];
} catch (Throwable $e) {
    $tiendas_hoy = 0;
    $tiendas_semana = 0;
    $semanas_mes = [];
}

$conn->close();

// porcentajes
$porc_dia = ($meta_dia > 0) ? min(100, (int)round(($tiendas_hoy / $meta_dia) * 100)) : 0;
$porc_semana = ($meta_semana > 0) ? min(100, (int)round(($tiendas_semana / $meta_semana) * 100)) : 0;

// semanas del mes (para calendario)
[$weeksToShow, $weekLabels] = buildWeeksForMonth($monthStart, $monthEndExclusive);

// render vista
require __DIR__ . '/views/principal_view.php';
