<?php
// reports/usuarios_detalle.php

return [
  'page_title' => "Reporte: Actividad por Usuario (Desglosado)",
  'extra_note' => "Primero muestra la tienda, debajo los registros y en el encabezado los comentarios.",

  // ✅ SOLO usamos t.foto_estanteria (porque es la única foto que existe)
  // ✅ Orden por tienda para que el agrupado sea correcto
  'sql' => "
    SELECT
      p.tienda_id,
      u.nombre_completo AS usuario,
      t.nombre_tienda AS tienda,

      t.foto_estanteria AS foto_estanteria,

      p.marca,
      p.bloom,
      p.presentacion,
      p.precio,
      DATE(p.fecha_captura) AS fecha
    FROM productos_capturados p
    LEFT JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN tiendas t ON p.tienda_id = t.id
    $where
    ORDER BY p.fecha_captura DESC
    LIMIT 900
  ",

  // ✅ En este reporte NO hay foto por captura, entonces NO existe foto_captura aquí
  'columns_detalle_productos' => [
    'usuario' => 'Usuario',
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'precio' => 'Precio',
    'fecha' => 'Fecha',
  ],

  // (Si tu sistema usa 'columns' para el header general de tabla, lo dejamos igual)
  'columns' => [
    'usuario' => 'Usuario',
    'marca' => 'Marca',
    'bloom' => 'Bloom',
    'presentacion' => 'Presentación',
    'precio' => 'Precio',
    'fecha' => 'Fecha',
  ],

  'needs_comments' => true,
];
