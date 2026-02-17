<?php
require_once __DIR__ . '/db_helpers.php';

function getPrincipalMetrics(mysqli $conn, int $usuario_id, string $fecha_col, string $monthStart, string $monthEndExclusive): array {
    $tiendas_hoy = 0;
    $tiendas_semana = 0;
    $semanas_mes = [];

    // ✅ Seguridad mínima: solo permitir columnas conocidas
    $allowedCols = ['fecha_registro', 'created_at', 'fecha']; // ajusta si aplica
    if (!in_array($fecha_col, $allowedCols, true)) {
        $fecha_col = 'fecha_registro';
    }

    // Hoy
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM tiendas
        WHERE usuario_id = ?
          AND DATE($fecha_col) = CURDATE()
    ");
    if ($stmt) {
        $stmt->bind_param("i", $usuario_id);
        $tiendas_hoy = fetch_one_int($stmt, 'total');
        $stmt->close();
    }

    // Semana ISO actual
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM tiendas
        WHERE usuario_id = ?
          AND YEARWEEK($fecha_col, 1) = YEARWEEK(CURDATE(), 1)
    ");
    if ($stmt) {
        $stmt->bind_param("i", $usuario_id);
        $tiendas_semana = fetch_one_int($stmt, 'total');
        $stmt->close();
    }

    // Totales por semana del mes (ISO)
    $stmt = $conn->prepare("
        SELECT YEARWEEK($fecha_col, 1) AS yw, COUNT(*) AS total
        FROM tiendas
        WHERE usuario_id = ?
          AND $fecha_col >= ?
          AND $fecha_col < ?
        GROUP BY YEARWEEK($fecha_col, 1)
        ORDER BY yw ASC
    ");
    if ($stmt) {
        $stmt->bind_param("iss", $usuario_id, $monthStart, $monthEndExclusive);
        $stmt->execute();

        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $semanas_mes[(int)$row['yw']] = (int)$row['total'];
            }
        } else {
            $stmt->bind_result($yw, $total);
            while ($stmt->fetch()) {
                $semanas_mes[(int)$yw] = (int)$total;
            }
        }
        $stmt->close();
    }

    return [
        'tiendas_hoy' => $tiendas_hoy,
        'tiendas_semana' => $tiendas_semana,
        'semanas_mes' => $semanas_mes,
    ];
}
