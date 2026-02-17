<?php
// Este reporte cuenta TIENDAS, pero permite filtrar por productos usando EXISTS.
// IMPORTANTE: aquí asumimos que en reportes.php ya existe $filters con:
// $filters['fecha_inicio'], $filters['fecha_fin'], $filters['usuario_filtro'],
// $filters['tienda_id'], $filters['marca_filtro'], $filters['bloom_filtro'], $filters['presentacion_filtro']

$whereUsuarios = " WHERE 1=1 ";

$paramsU = [];
$typesU  = "";

// Filtros que aplican a TIENDAS (t)
if (!empty($filters['usuario_filtro'])) {
  $whereUsuarios .= " AND t.usuario_id = ? ";
  $paramsU[] = (int)$filters['usuario_filtro'];
  $typesU .= "i";
}

if (!empty($filters['tienda_id'])) {
  $whereUsuarios .= " AND t.id = ? ";
  $paramsU[] = (int)$filters['tienda_id'];
  $typesU .= "i";
}

// Fecha: aquí puedes decidir si filtras por fecha_registro de tienda
// (recomendado si quieres "tiendas capturadas").
// Si quieres que la fecha sea la de captura del producto, te digo cómo abajo.
if (!empty($filters['fecha_inicio'])) {
  $whereUsuarios .= " AND DATE(t.fecha_registro) >= ? ";
  $paramsU[] = $filters['fecha_inicio'];
  $typesU .= "s";
}
if (!empty($filters['fecha_fin'])) {
  $whereUsuarios .= " AND DATE(t.fecha_registro) <= ? ";
  $paramsU[] = $filters['fecha_fin'];
  $typesU .= "s";
}

/*
  ✅ Filtros por PRODUCTOS pero sin duplicar tiendas:
  Solo filtra tiendas si existe al menos un producto capturado que cumpla.
*/
$needsExists = (
  !empty($filters['marca_filtro']) ||
  !empty($filters['bloom_filtro']) ||
  !empty($filters['presentacion_filtro'])
);

// Si quieres que los filtros de fecha (inicio/fin) sean por fecha_captura del producto
// en vez de fecha_registro de tienda, activa este flag:
$fechaPorProducto = false; // <-- cambia a true si lo quieres así

if ($needsExists || $fechaPorProducto) {
  $whereUsuarios .= " AND EXISTS (
      SELECT 1
      FROM productos_capturados p
      WHERE p.tienda_id = t.id
  ";

  if (!empty($filters['marca_filtro'])) {
    $whereUsuarios .= " AND p.marca = ? ";
    $paramsU[] = $filters['marca_filtro'];
    $typesU .= "s";
  }
  if (!empty($filters['bloom_filtro'])) {
    $whereUsuarios .= " AND p.bloom = ? ";
    $paramsU[] = $filters['bloom_filtro'];
    $typesU .= "s";
  }
  if (!empty($filters['presentacion_filtro'])) {
    $whereUsuarios .= " AND p.presentacion = ? ";
    $paramsU[] = $filters['presentacion_filtro'];
    $typesU .= "s";
  }

  if ($fechaPorProducto) {
    if (!empty($filters['fecha_inicio'])) {
      $whereUsuarios .= " AND DATE(p.fecha_captura) >= ? ";
      $paramsU[] = $filters['fecha_inicio'];
      $typesU .= "s";
    }
    if (!empty($filters['fecha_fin'])) {
      $whereUsuarios .= " AND DATE(p.fecha_captura) <= ? ";
      $paramsU[] = $filters['fecha_fin'];
      $typesU .= "s";
    }
  }

  $whereUsuarios .= " ) ";
}

return [
  'page_title' => "Reporte: Actividad de Usuarios (Resumen)",
  'extra_note' => "Totaliza tiendas capturadas por usuario. (Filtra por marca/bloom/presentación sin duplicar tiendas.)",

  // ⚠️ Aquí ya NO uses $where genérico de productos, usa $whereUsuarios
  'sql' => "
    SELECT
      u.nombre_completo AS usuario,
      COUNT(DISTINCT t.id) AS total_tiendas,
      COUNT(DISTINCT DATE(t.fecha_registro)) AS dias_activos,
      MIN(DATE(t.fecha_registro)) AS primera_captura,
      MAX(DATE(t.fecha_registro)) AS ultima_captura
    FROM tiendas t
    LEFT JOIN usuarios u ON t.usuario_id = u.id
    $whereUsuarios
    GROUP BY u.nombre_completo
    ORDER BY total_tiendas DESC
  ",

  'columns' => [
    'usuario' => 'Usuario',
    'total_tiendas' => 'Total Tiendas',
    'dias_activos' => 'Días Activos',
    'primera_captura' => 'Primera Captura',
    'ultima_captura' => 'Última Captura'
  ],

  'columns_detalle_productos' => [],
  'needs_comments' => false,

  // ✅ para que reportes.php use estos params/types en vez de los del builder
  'override_params' => $paramsU,
  'override_types'  => $typesU,
];
