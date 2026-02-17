<?php
function fetch_one_int(mysqli_stmt $stmt, string $alias = 'total'): int {
    $stmt->execute();

    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        return (int)($row[$alias] ?? 0);
    }

    $val = 0;
    $stmt->bind_result($val);
    $stmt->fetch();
    return (int)$val;
}
