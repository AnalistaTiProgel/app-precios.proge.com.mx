<?php
return [
  'page_title' => "Reporte: Actividad por Usuario (Desglosado)",
  'extra_note' => "Primero muestra la tienda, debajo las capturas y en el encabezado los comentarios.",

  'sql' => "
    SELECT
      p.tienda_id,
      u.nombre_completo as usuario,
      t.nombre_tienda as tienda,

      t.foto_estanteria as foto_estanteria,   -- foto tienda
      p.foto_captura as foto_captura,         -- ✅ foto de la captura (CAMBIAR si tu campo tiene otro nombre)

      p.marca,
      p.bloom,
      p.presentacion,
      p.precio,
      DATE(p.fecha_captura) as fecha
    FROM productos_capturados p
    LEFT JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN tiendas t ON p.tienda_id = t.id
    $where
    ORDER BY p.fecha_captura DESC, t.nombre_tienda ASC
    LIMIT 900
  ",

  'columns_detalle_productos' => [
    'usuario' => 'Usuario',
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'precio' => 'Precio',
    'fecha' => 'Fecha',
    'foto_captura' => 'Foto Captura'       
  ],

  'columns' => [
    'usuario' => 'Usuario',
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'precio' => 'Precio',
    'fecha' => 'Fecha',
    'foto_captura' => 'Foto Captura'       
  ],

  'needs_comments' => true,
];
