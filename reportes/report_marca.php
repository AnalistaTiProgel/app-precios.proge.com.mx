<?php
return [
  'page_title' => "Reporte: Precios Promedio por Marca + Bloom + Presentación",
  'extra_note' => "Agrupa por marca, bloom y presentación.",

  'sql' => "
    SELECT 
      p.marca,
      COALESCE(p.bloom,'N/A') as bloom,
      COALESCE(p.presentacion,'N/A') as presentacion,
      COUNT(*) as total_productos,
      MIN(p.precio) as precio_minimo,
      MAX(p.precio) as precio_maximo,
      AVG(p.precio) as precio_promedio,
      MAX(DATE(p.fecha_captura)) as ultima_captura
    FROM productos_capturados p
    $where
    GROUP BY p.marca, p.bloom, p.presentacion
    ORDER BY p.marca ASC, p.bloom ASC, p.presentacion ASC
  ",

  'columns' => [
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'total_productos' => 'Total Registros',
    'precio_minimo' => 'Precio Mínimo',
    'precio_maximo' => 'Precio Máximo',
    'precio_promedio' => 'Precio Promedio',
    'ultima_captura' => 'Última Captura'
  ],

  'columns_detalle_productos' => [],
  'needs_comments' => false,
];
