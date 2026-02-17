<?php
function loadCatalogos(mysqli $conn): array {
    $marcas = [];
    $res = $conn->query("SELECT DISTINCT marca FROM productos_capturados ORDER BY marca");
    while ($res && ($r = $res->fetch_assoc())) $marcas[] = $r['marca'];

    $tiendas = [];
    $res = $conn->query("SELECT id, nombre_tienda FROM tiendas ORDER BY nombre_tienda");
    while ($res && ($r = $res->fetch_assoc())) $tiendas[] = $r;

    $blooms = [];
    $res = $conn->query("SELECT DISTINCT bloom FROM productos_capturados ORDER BY bloom");
    while ($res && ($r = $res->fetch_assoc())) $blooms[] = $r['bloom'];

    $presentaciones = [];
    $res = $conn->query("SELECT DISTINCT presentacion FROM productos_capturados ORDER BY presentacion");
    while ($res && ($r = $res->fetch_assoc())) $presentaciones[] = $r['presentacion'];

    $usuarios = [];
    $res = $conn->query("SELECT id, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo");
    while ($res && ($r = $res->fetch_assoc())) $usuarios[] = $r;

    return compact('marcas','tiendas','blooms','presentaciones','usuarios');
}
