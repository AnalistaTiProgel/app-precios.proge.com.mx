<?php
function fetchComentariosPorTienda(mysqli $conn, array $tiendaIds, array $filters): array {
    $comentariosPorTienda = [];
    if (empty($tiendaIds)) return $comentariosPorTienda;

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

    if (!empty($filters['fecha_inicio'])) {
        $sqlC .= " AND DATE(c.fecha_registro) >= ? ";
        $paramsC[] = $filters['fecha_inicio'];
        $typesC .= "s";
    }
    if (!empty($filters['fecha_fin'])) {
        $sqlC .= " AND DATE(c.fecha_registro) <= ? ";
        $paramsC[] = $filters['fecha_fin'];
        $typesC .= "s";
    }
    if (!empty($filters['usuario_filtro'])) {
        $sqlC .= " AND c.usuario_id = ? ";
        $paramsC[] = (int)$filters['usuario_filtro'];
        $typesC .= "i";
    }

    $sqlC .= " ORDER BY c.fecha_registro DESC";

    $stmtC = $conn->prepare($sqlC);
    if (!$stmtC) return $comentariosPorTienda;

    $stmtC->bind_param($typesC, ...$paramsC);
    $stmtC->execute();
    $resC = $stmtC->get_result();

    while ($rowC = $resC->fetch_assoc()) {
        $tid = (int)($rowC['tienda_id'] ?? 0);
        if (!$tid) continue;

        if (!isset($comentariosPorTienda[$tid])) $comentariosPorTienda[$tid] = [];
        $comentariosPorTienda[$tid][] = [
            'fecha' => $rowC['fecha'] ?? '',
            'usuario' => $rowC['usuario'] ?? '—',
            'comentario' => $rowC['comentario'] ?? ''
        ];
    }

    $stmtC->close();
    return $comentariosPorTienda;
}
