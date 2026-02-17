<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captura de Precios - Principal</title>

    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/utilities.css">
    <link rel="stylesheet" href="css/principal.css">
</head>
<body>

<header class="header">
    <div class="header-left">
        <div class="user-greeting">
            <span class="user-avatar"><?php echo strtoupper(substr($usuario_nombre, 0, 1)); ?></span>
            <span class="user-name"><?php echo htmlspecialchars($usuario_nombre); ?></span>
        </div>
    </div>

    <div class="header-center">
        <div class="company-logo header-logo">
            <img src="images/progel.png" alt="Logo Progel">
        </div>
        <div class="logo">Captura de Precios</div>
    </div>

    <div class="header-user">
        <a href="logout.php" class="btn btn-danger">🚪 Cerrar Sesión</a>
    </div>
</header>

<main class="main-container">

    <div class="feature-card feature-card-wide">
        <div class="feature-icon">🎯</div>
        <h3 class="feature-title">Metas de captura</h3>
        <p class="feature-description">Avance de tiendas capturadas por tu usuario.</p>

        <div class="goal-grid">
            <div class="goal-card">
                <div class="goal-header">
                    <span class="goal-label">Hoy</span>
                    <span class="goal-value"><?php echo (int)$tiendas_hoy; ?>/<?php echo (int)$meta_dia; ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo (int)$porc_dia; ?>%;"></div>
                </div>
                <div class="goal-footer"><?php echo (int)$porc_dia; ?>% completado</div>
            </div>

            <div class="goal-card">
                <div class="goal-header">
                    <span class="goal-label">Semana</span>
                    <span class="goal-value"><?php echo (int)$tiendas_semana; ?>/<?php echo (int)$meta_semana; ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo (int)$porc_semana; ?>%;"></div>
                </div>
                <div class="goal-footer"><?php echo (int)$porc_semana; ?>% completado</div>
            </div>
        </div>
    </div>

    <div class="features-grid">

        <a href="index.php" class="feature-card feature-link">
            <div class="feature-icon">🏪</div>
            <h3 class="feature-title">Capturar Tienda</h3>
            <p class="feature-description">
                Registra nueva información de tiendas de la competencia con ubicación GPS, fotos y detalles completos.
            </p>
        </a>

        <a href="reportes.php" class="feature-card feature-link">
            <div class="feature-icon">📊</div>
            <h3 class="feature-title">Reportes</h3>
            <p class="feature-description">
                Consulta precios promedio, análisis detallados y exporta reportes fácilmente.
            </p>
        </a>

        <div class="feature-card compliance-card">
            <div class="feature-icon">🗓️</div>
            <h3 class="feature-title">Cumplimiento mensual</h3>
            <p class="feature-description">
                Meta semanal: <strong><?php echo (int)$weeklyTarget; ?></strong> capturas.
            </p>

            <div class="calendar-grid">
                <?php foreach ($weeksToShow as $yw):
                    $total = $semanas_mes[$yw] ?? 0;
                    $ok = ($total >= $weeklyTarget);
                    $isCurrent = ($yw === $currentYearWeekIso);
                ?>
                    <div class="week-cell <?php echo $isCurrent ? 'is-current' : ''; ?>">
                        <div class="week-top">
                            <span class="week-label"><?php echo htmlspecialchars($weekLabels[$yw] ?? 'Semana'); ?></span>
                            <span class="week-status <?php echo $ok ? 'ok' : 'bad'; ?>">
                                <?php echo $ok ? '✅' : '❌'; ?>
                            </span>
                        </div>
                        <div class="week-meta">
                            <?php echo (int)$total; ?>/<?php echo (int)$weeklyTarget; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

</body>
</html>
