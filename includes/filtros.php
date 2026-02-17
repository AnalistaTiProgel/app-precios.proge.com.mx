<?php
function buildWhereFromFilters(array $get, array $opts = []): array {
    // Alias/tablas por defecto (productos_capturados)
    $alias = $opts['alias'] ?? 'p';                         // 'p' o 't'
    $dateCol = $opts['date_col'] ?? 'fecha_captura';        // fecha_captura o fecha_registro

    // Qué filtros aplicar (por si un reporte NO tiene bloom/presentacion, etc.)
    $allow = $opts['allow'] ?? [
        'marca' => true,
        'bloom' => true,
        'presentacion' => true,
        'fecha' => true,
        'tienda' => true,
        'usuario' => true,
    ];

    $marca_filtro = $get['marca'] ?? '';
    $fecha_inicio = $get['fecha_inicio'] ?? '';
    $fecha_fin    = $get['fecha_fin'] ?? '';
    $tienda_id    = $get['tienda_id'] ?? '';

    $bloom_filtro        = $get['bloom'] ?? '';
    $presentacion_filtro = $get['presentacion'] ?? '';
    $usuario_filtro      = $get['usuario_id'] ?? '';

    $where  = " WHERE 1=1 ";
    $params = [];
    $types  = "";

    // ✅ Marca/Bloom/Presentación normalmente viven en productos (p).
    // Si el reporte es de tiendas (alias t), estos filtros no aplican (se ignoran).
    if (!empty($allow['marca']) && $marca_filtro !== '' && $alias === 'p') {
        $where .= " AND {$alias}.marca = ? ";
        $params[] = $marca_filtro; $types .= "s";
    }
    if (!empty($allow['bloom']) && $bloom_filtro !== '' && $alias === 'p') {
        $where .= " AND {$alias}.bloom = ? ";
        $params[] = $bloom_filtro; $types .= "s";
    }
    if (!empty($allow['presentacion']) && $presentacion_filtro !== '' && $alias === 'p') {
        $where .= " AND {$alias}.presentacion = ? ";
        $params[] = $presentacion_filtro; $types .= "s";
    }

    // ✅ Fecha: usa la columna configurada
    if (!empty($allow['fecha']) && $fecha_inicio !== '') {
        $where .= " AND DATE({$alias}.{$dateCol}) >= ? ";
        $params[] = $fecha_inicio; $types .= "s";
    }
    if (!empty($allow['fecha']) && $fecha_fin !== '') {
        $where .= " AND DATE({$alias}.{$dateCol}) <= ? ";
        $params[] = $fecha_fin; $types .= "s";
    }

    // ✅ tienda_id normalmente aplica a productos (p.tienda_id).
    // En tiendas (t) sería t.id.
    if (!empty($allow['tienda']) && $tienda_id !== '') {
        if ($alias === 'p') {
            $where .= " AND {$alias}.tienda_id = ? ";
        } else { // alias t
            $where .= " AND {$alias}.id = ? ";
        }
        $params[] = (int)$tienda_id; $types .= "i";
    }

    // ✅ usuario_id existe tanto en productos (p.usuario_id) como en tiendas (t.usuario_id)
    if (!empty($allow['usuario']) && $usuario_filtro !== '') {
        $where .= " AND {$alias}.usuario_id = ? ";
        $params[] = (int)$usuario_filtro; $types .= "i";
    }

    return [
        'where' => $where,
        'params' => $params,
        'types' => $types,
        'filters' => [
            'marca_filtro' => $marca_filtro,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'tienda_id' => $tienda_id,
            'bloom_filtro' => $bloom_filtro,
            'presentacion_filtro' => $presentacion_filtro,
            'usuario_filtro' => $usuario_filtro,
        ],
    ];
}
