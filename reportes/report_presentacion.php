<?php
return [
  'page_title' => "Reporte: Por Presentación + Bloom (historial)",
  'extra_note' => "Agrupa por marca + bloom + presentacion + fecha.",
  'sql' => "
    SELECT
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
    LIMIT 800
  ",
  'columns' => [
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'fecha' => 'Fecha',
    'precio_minimo' => 'Precio Mínimo',
    'precio_maximo' => 'Precio Máximo',
    'precio_promedio' => 'Precio Promedio',
    'capturas' => 'Capturas'
  ],
  'columns_detalle_productos' => [],
  'needs_comments' => false,
];
