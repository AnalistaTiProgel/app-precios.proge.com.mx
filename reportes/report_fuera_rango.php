<?php
return [
  'page_title' => "Reporte: Capturas Fuera de Rango (posibles errores)",
  'extra_note' => "Muestra la CAPTURA (registro) fuera de rango por Marca + Bloom + Presentación.",
  'sql' => "
    SELECT
      p.id AS captura_id,
      p.tienda_id,
      u.nombre_completo AS usuario,

      p.marca,
      p.bloom,
      p.presentacion,

      t.nombre_tienda AS tienda,
      t.foto_estanteria AS foto_estanteria,

      p.precio,
      DATE(p.fecha_captura) AS fecha,

      stats.promedio_grupo,
      stats.std_grupo,
      stats.total_grupo

    FROM productos_capturados p
    LEFT JOIN tiendas t ON p.tienda_id = t.id
    LEFT JOIN usuarios u ON p.usuario_id = u.id

    JOIN (
      SELECT
        marca,
        bloom,
        presentacion,
        AVG(precio) AS promedio_grupo,
        STDDEV_SAMP(precio) AS std_grupo,
        COUNT(*) AS total_grupo
      FROM productos_capturados
      WHERE precio IS NOT NULL
        AND precio > 0
        AND marca IS NOT NULL AND marca <> ''
        AND bloom IS NOT NULL AND bloom <> ''
        AND presentacion IS NOT NULL AND presentacion <> ''
      GROUP BY marca, bloom, presentacion
      HAVING COUNT(*) >= 5
    ) stats
      ON p.marca = stats.marca
     AND p.bloom = stats.bloom
     AND p.presentacion = stats.presentacion

    $where
    AND stats.std_grupo IS NOT NULL
    AND (
      p.precio > (stats.promedio_grupo + 2*stats.std_grupo)
      OR p.precio < (stats.promedio_grupo - 2*stats.std_grupo)
    )

    ORDER BY p.fecha_captura DESC
    LIMIT 600
  ",
  'columns' => [
    'captura_id' => 'Captura ID',
    'usuario' => 'Usuario',

    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',

    'tienda' => 'Tienda',
    'precio' => 'Precio',
    'fecha' => 'Fecha',

    'promedio_grupo' => 'Promedio Grupo',
    'std_grupo' => 'STD Grupo',
    'total_grupo' => 'Muestras',

    'foto_estanteria' => 'Foto Estantería'
  ],
  'columns_detalle_productos' => [],
  'needs_comments' => false,
];
