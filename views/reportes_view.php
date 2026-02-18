<?php

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Captura de Precios</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- ✅ Como esta vista está en /views, subimos 1 nivel -->
    <link rel="stylesheet" href="./css/base.css">
    <link rel="stylesheet" href="./css/layout.css">
    <link rel="stylesheet" href="./css/components.css">
    <link rel="stylesheet" href="./css/utilities.css">
    <link rel="stylesheet" href="./css/principal.css">
    <link rel="stylesheet" href="./css/reportes.css">
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <i class="fas fa-chart-line"></i> Reportes de Precios
        </div>

        <div class="user-info">
<div class="dropdown" id="reportesDropdown">
  <button type="button" class="btn btn-secondary dropdown-toggle" id="btnReportes" aria-haspopup="true" aria-expanded="false">
    <i class="fas fa-folder-open"></i> Reportes
    <span class="badge-mini"><?php echo htmlspecialchars($reporte_tipo); ?></span>
  </button>

  <div class="dropdown-menu" id="menuReportes" role="menu" aria-labelledby="btnReportes">
                    <a href="<?php echo htmlspecialchars($self); ?>?reporte=marca"><i class="fas fa-tags"></i> Precios por Marca</a>
                    <a href="<?php echo htmlspecialchars($self); ?>?reporte=top"><i class="fas fa-ranking-star"></i> Top Caras / Baratas</a>
                    <a href="<?php echo htmlspecialchars($self); ?>?reporte=presentacion"><i class="fas fa-boxes-stacked"></i> Por Presentación + Bloom</a>
                    <a href="<?php echo htmlspecialchars($self); ?>?reporte=usuarios"><i class="fas fa-user-clock"></i> Actividad de Usuarios (Resumen)</a>
                    <a href="<?php echo htmlspecialchars($self); ?>?reporte=usuarios_detalle"><i class="fas fa-camera"></i> Actividad por Usuario (Desglosado)</a>
                    <a href="<?php echo htmlspecialchars($self); ?>?reporte=fuera_rango"><i class="fas fa-triangle-exclamation"></i> Fuera de Rango</a>
                </div>
            </div>

            <span class="user-name">👋 <?php echo htmlspecialchars($usuario_nombre); ?></span>
            <a href="../principal.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Inicio
            </a>
        </div>
    </div>
</div>

<div class="main-container">

    <div class="report-hero">
        <h1 class="page-title">
            <i class="fas fa-chart-bar"></i> <?php echo htmlspecialchars($page_title); ?>
        </h1>
        <?php if (!empty($extra_note)): ?>
            <p class="report-subtitle">
                <i class="fas fa-circle-info"></i> <?php echo htmlspecialchars($extra_note); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="filters-container">
        <h2 class="filters-title">
            <i class="fas fa-filter"></i> Filtros de Búsqueda
        </h2>

        <form method="GET" action="<?php echo htmlspecialchars($self); ?>">
            <input type="hidden" name="reporte" value="<?php echo htmlspecialchars($reporte_tipo); ?>">

            <div class="filters-grid">

                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <select name="usuario_id" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>"
                                <?php echo ($filters['usuario_filtro'] !== '' && (int)$filters['usuario_filtro'] === (int)$u['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Marca</label>
                    <select name="marca" class="form-control">
                        <option value="">Todas</option>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>"
                                <?php echo ($filters['marca_filtro'] === $m) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Bloom</label>
                    <select name="bloom" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach ($blooms as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"
                                <?php echo ($filters['bloom_filtro'] === $b) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Presentación</label>
                    <select name="presentacion" class="form-control">
                        <option value="">Todas</option>
                        <?php foreach ($presentaciones as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>"
                                <?php echo ($filters['presentacion_filtro'] === $p) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control"
                           value="<?php echo htmlspecialchars($filters['fecha_inicio']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="form-control"
                           value="<?php echo htmlspecialchars($filters['fecha_fin']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Tienda</label>
                    <select name="tienda_id" class="form-control">
                        <option value="">Todas</option>
                        <?php foreach ($tiendas as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>"
                                <?php echo ($filters['tienda_id'] !== '' && (int)$filters['tienda_id'] === (int)$t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['nombre_tienda']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Aplicar Filtros
                </button>

                <a href="<?php echo htmlspecialchars($self); ?>?reporte=<?php echo urlencode($reporte_tipo); ?>" class="btn btn-gray">
                    <i class="fas fa-redo"></i> Limpiar
                </a>
            </div>
        </form>
    </div>

    <div class="report-container">
        <?php if (!empty($reportes)): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $key => $label): ?>
                                <th><?php echo htmlspecialchars($label); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($reporte_tipo === 'usuarios_detalle'): ?>

                        <?php
                        $grupos = [];
                        foreach ($reportes as $r) {
                            $tid = (int)($r['tienda_id'] ?? 0);
                            $tiendaNombre = $r['tienda'] ?? 'Sin tienda';

                            if (!isset($grupos[$tid])) {
                                $grupos[$tid] = [
                                    'nombre' => $tiendaNombre,
                                    'foto' => $r['foto_estanteria'] ?? '',
                                    'rows' => []
                                ];
                            }
                            $grupos[$tid]['rows'][] = $r;
                        }

                        $colspan = count($columns_detalle_productos);

                        foreach ($grupos as $tid => $data):
                            $fotoUrl = photo_public_url((string)($data['foto'] ?? ''));
                            $comentarios = $comentariosPorTienda[$tid] ?? [];
                        ?>
                            <tr class="group-row">
                                <td colspan="<?php echo (int)$colspan; ?>">

                                    <!-- ✅ HEADER CON FOTO A LA DERECHA -->
                                    <div class="group-header" style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px;">

                                        <!-- IZQUIERDA: TITULO + META + COMENTARIOS -->
                                        <div style="flex:1;">
                                            <div class="group-title">
                                                🏪 <?php echo htmlspecialchars($data['nombre']); ?>
                                                <span class="group-meta">
                                                    | Registros: <?php echo count($data['rows']); ?>
                                                </span>
                                            </div>

                                            <div class="group-comments" style="margin-top:8px;">
                                                <b>Comentarios:</b>
                                                <?php if (!empty($comentarios)): ?>
                                                    <ul>
                                                        <?php
                                                        $max = 3; $i = 0;
                                                        foreach ($comentarios as $c) {
                                                            $i++; if ($i > $max) break;
                                                            $f = !empty($c['fecha']) ? date('d/m/Y H:i', strtotime($c['fecha'])) : '';
                                                            $u = $c['usuario'] ?? '—';
                                                            $txt = trim((string)($c['comentario'] ?? ''));
                                                        ?>
                                                            <li>
                                                                <span class="comment-user"><?php echo htmlspecialchars($u); ?></span>:
                                                                <?php echo htmlspecialchars($txt); ?>
                                                                <?php if ($f): ?>
                                                                    <span class="comment-date">(<?php echo htmlspecialchars($f); ?>)</span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php } ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <div class="img-empty">Sin comentarios</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- DERECHA: FOTO -->
                                        <div>
                                            <?php if ($fotoUrl): ?>
                                                <a href="<?php echo htmlspecialchars($fotoUrl); ?>" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">
                                                    <img
                                                        src="<?php echo htmlspecialchars($fotoUrl); ?>"
                                                        loading="lazy"
                                                        decoding="async"
                                                        width="120"
                                                        height="120"
                                                        alt="foto estantería"
                                                        class="thumb"
                                                        style="border-radius:10px; object-fit:cover;"
                                                    >
                                                </a>
                                            <?php else: ?>
                                                <span class="img-empty">Sin foto</span>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </td>
                            </tr>

                            <?php foreach ($data['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($columns_detalle_productos as $key => $label): ?>
                                        <td>
                                            <?php
                                            $val = $row[$key] ?? null;

                                            if ($key === 'precio') {
                                                echo fmt_money($val);

                                            } elseif ($key === 'fecha') {
                                                echo !empty($val) ? date('d/m/Y', strtotime($val)) : 'N/A';

                                            } else {
                                                echo htmlspecialchars((string)$val);
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <?php foreach ($reportes as $row): ?>
                            <tr>
                                <?php foreach ($columns as $key => $label): ?>
                                    <td>
                                        <?php
                                        $val = $row[$key] ?? null;

                                        if (in_array($key, $moneyKeys, true)) {
                                            echo fmt_money($val);
                                        } elseif (in_array($key, $dateKeys, true)) {
                                            echo !empty($val) ? date('d/m/Y', strtotime($val)) : 'N/A';
                                        } elseif ($key === 'std' || $key === 'std_marca') {
                                            echo ($val === null ? 'N/A' : number_format((float)$val, 2));
                                        } elseif (in_array($key, $intKeys, true)) {
                                            echo ($val === null || $val === '' ? '0' : (int)$val);
                                        } elseif ($key === 'foto_estanteria') {
                                            $url = photo_public_url((string)$val);
                                            if ($url) {
                                                $src = htmlspecialchars($url);
                                                echo '<a href="'.$src.'" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">';
                                                echo '<img src="'.$src.'" loading="lazy" decoding="async" width="70" height="70" alt="foto estantería" class="thumb">';
                                                echo '</a>';
                                            } else {
                                                echo '<span class="img-empty">Sin foto</span>';
                                            }
                                        } else {
                                            echo htmlspecialchars((string)$val);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="export-buttons">
                <a href="../exportar_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-file-pdf"></i> Exportar a PDF
                </a>
                <a href="../exportar_excel.php?<?php echo http_build_query($_GET); ?>" class="btn btn-excel" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-file-excel"></i> Exportar a Excel
                </a>
            </div>

        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-chart-bar"></i>
                <h3>No hay datos para mostrar</h3>
                <p>No se encontraron resultados con los filtros aplicados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const today = new Date().toISOString().split('T')[0];
    const fin = document.querySelector('input[name="fecha_fin"]');
    const ini = document.querySelector('input[name="fecha_inicio"]');
    if (fin) fin.max = today;
    if (ini) ini.max = today;

    document.querySelector('form')?.addEventListener('submit', function(e) {
        const fechaInicio = ini ? ini.value : '';
        const fechaFin = fin ? fin.value : '';

        if (fechaInicio && fechaFin && fechaInicio > fechaFin) {
            e.preventDefault();
            alert('La fecha de inicio no puede ser mayor a la fecha de fin');
        }
    });
</script>
<script>
  (function () {
    const dd = document.getElementById('reportesDropdown');
    const btn = document.getElementById('btnReportes');

    if (!dd || !btn) return;

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const isOpen = dd.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Cerrar al hacer click fuera
    document.addEventListener('click', function () {
      dd.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    });

    // Evitar que al hacer click dentro del menú se cierre antes de seleccionar
    dd.querySelector('.dropdown-menu')?.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    // Cerrar con ESC
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        dd.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  })();
</script>

</body>
</html>
