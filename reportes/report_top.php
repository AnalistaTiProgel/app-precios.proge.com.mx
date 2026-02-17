<?php
return [
  'page_title' => "Reporte: Top (Más caras / Más baratas) por Marca + Bloom + Presentación",
  'extra_note' => "Calcula el promedio por combinación Marca + Bloom + Presentación.",

  'sql' => "
    SELECT * FROM (
      SELECT 
        'Top Caras' as grupo,
        p.marca,
        COALESCE(p.bloom,'N/A') as bloom,
        COALESCE(p.presentacion,'N/A') as presentacion,
        AVG(p.precio) as precio_promedio,
        COUNT(*) as total_registros
      FROM productos_capturados p
      $where
      GROUP BY p.marca, p.bloom, p.presentacion
      ORDER BY precio_promedio DESC
      LIMIT 10
    ) A
    UNION ALL
    SELECT * FROM (
      SELECT 
        'Top Baratas' as grupo,
        p.marca,
        COALESCE(p.bloom,'N/A') as bloom,
        COALESCE(p.presentacion,'N/A') as presentacion,
        AVG(p.precio) as precio_promedio,
        COUNT(*) as total_registros
      FROM productos_capturados p
      $where
      GROUP BY p.marca, p.bloom, p.presentacion
      ORDER BY precio_promedio ASC
      LIMIT 10
    ) B
  ",

  'columns' => [
    'grupo' => 'Grupo',
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'precio_promedio' => 'Precio Promedio',
    'total_registros' => 'Total Registros'
  ],

  'columns_detalle_productos' => [],
  'needs_comments' => false,
];
