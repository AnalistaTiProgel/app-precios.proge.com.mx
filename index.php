<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'] ?? '';
$tienda_id = isset($_GET['tienda_id']) ? (int)$_GET['tienda_id'] : 0;

// ✅ una sola fuente para la key
$GOOGLE_KEY = 'TU_API_KEY_AQUI';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captura de Precios</title>

    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/utilities.css">
    <link rel="stylesheet" href="css/principal.css">
</head>
<body>

<header class="header">
    <div class="header-left">
        <?php if (!$tienda_id): ?>
            <a href="principal.php" class="btn btn-secondary">🏠 Inicio</a>
        <?php endif; ?>
    </div>

    <div class="header-center">
        <div class="company-logo header-logo">
            <img src="images/progel.png" alt="Logo Progel">
        </div>
        <div class="logo">
            <?php echo $tienda_id ? 'Captura de Precios' : 'Captura de Tienda'; ?>
        </div>
    </div>

    <div class="header-user">
        <a href="logout.php" class="btn btn-danger">🚪 Cerrar Sesión</a>
    </div>
</header>

<main class="main-container">

<!-- =========================
     FORMULARIO TIENDA
========================= -->
<section id="formularioTienda" <?php echo $tienda_id ? 'style="display:none;"' : ''; ?>>
<form id="formTienda" class="card">

<h2 class="card-title">Información de la Tienda</h2>

<div class="form-group">
    <label for="nombreTienda">Nombre de la Tienda y Sucursal</label>
    <input type="text" id="nombreTienda" name="nombreTienda" class="form-control" required>
</div>

<div class="form-group">
    <label for="direccion_manual">Dirección (captura manual)</label>
    <input type="text" id="direccion_manual" name="direccion_manual"
           class="form-control" placeholder="Escribe la dirección..." required>
</div>

<div class="form-group">
    <label for="direccion">Dirección detectada (GPS)</label>
    <input type="text" id="direccion" name="direccion"
           class="form-control" readonly>
</div>

<div class="form-group">
    <button type="button" id="btnUbicacion"
            class="btn btn-primary" onclick="obtenerUbicacion()">
        📍 Confirmar Ubicación Actual
    </button>
    <input type="hidden" id="latitud" name="latitud">
    <input type="hidden" id="longitud" name="longitud">
</div>

<!-- =========================
     FOTO ESTANTERÍA
========================= -->
<div class="form-group">
    <label>Foto de la Estantería</label>

    <!-- GALERÍA -->
    <input type="file"
           id="fotoEstanteriaGaleria"
           name="fotoEstanteria"
           accept="image/*"
           hidden>

    <!-- CÁMARA -->
    <input type="file"
           id="fotoEstanteriaCamara"
           accept="image/*"
           capture="environment"
           hidden>

    <!-- BOTONES -->
    <div id="fotoBotones"
         style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
        <button type="button" id="btnElegirGaleria" class="btn btn-secondary">
            🖼️ Elegir de galería
        </button>
        <button type="button" id="btnTomarFoto" class="btn btn-secondary">
            📸 Tomar foto
        </button>
    </div>

    <!-- PREVIEW -->
    <div id="vistaPreviaContainer" class="mt-20 hidden">
        <img id="vistaPrevia"
             alt="Vista previa"
             style="max-width:100%; border-radius:12px;">
        <button type="button" id="btnQuitarFoto"
                class="btn btn-danger mt-10">
            🗑️ Quitar Foto
        </button>
    </div>
</div>

<div class="mt-60 text-center">
    <button type="submit" class="btn btn-success">
        💾 Guardar Tienda y Agregar Productos
    </button>
</div>

</form>
</section>

<!-- =========================
     SECCIÓN PRODUCTOS
========================= -->
<section id="seccionProductos" <?php echo $tienda_id ? '' : 'style="display:none;"'; ?>>

<form id="formProducto" class="card">
<input type="hidden" id="tiendaId" name="tiendaId"
       value="<?php echo htmlspecialchars((string)$tienda_id); ?>">

<div class="grid grid-2">

<div class="form-group">
<label>Marca</label>
<select id="marca" name="marca" class="form-control" required>
<option value="">Seleccionar...</option>
<option>Deiman</option><option>Dgari</option><option>Duche</option>
<option>Gelcentro</option><option>Knox</option><option>La regia</option>
<option>La reina</option><option>PB Gelatin</option><option>Pilsac</option>
<option>Progel</option><option>Rousselot</option><option>Sanofi</option>
<option>Wilson (gelita)</option><option>Otra</option>
</select>
</div>

<div class="form-group">
<label>Bloom</label>
<select id="bloom" name="bloom" class="form-control" required>
<option value="">Seleccionar...</option>
<option>100</option><option>150</option><option>200</option><option>250</option>
<option>265</option><option>275</option><option>280</option><option>290</option>
<option>300</option><option>310</option><option>315</option><option>350</option>
</select>
</div>

<div class="form-group">
<label>Presentación</label>
<select id="presentacion" name="presentacion" class="form-control" required>
<option value="">Seleccionar...</option>
<option value="28g">28 g</option><option value="100g">100 g</option>
<option value="250g">250 g</option><option value="500g">500 g</option>
<option value="1kg">1 kg</option><option value="5kg">5 kg</option>
<option value="10kg">10 kg</option><option value="25kg">25 kg</option>
</select>
</div>

<div class="form-group">
<label>Precio</label>
<input type="number" id="precio" name="precio"
       class="form-control" step="0.01" min="0" required>
</div>

</div>

<div class="mt-30 text-center">
<button type="submit" class="btn btn-primary">➕ Agregar Producto</button>
</div>

<div id="listaProductos" class="mt-30"></div>

<div class="mt-30 text-center">
<button type="button" id="btnFinalizar"
        class="btn btn-success" onclick="finalizarCaptura()">
✅ Finalizar Captura
</button>
</div>

</form>
</section>

</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
window.__TIENDA_ID__ = <?php echo json_encode($tienda_id); ?>;
window.__GOOGLE_KEY__ = <?php echo json_encode($GOOGLE_KEY); ?>;
window.__BASE_PATH__ = <?php echo json_encode(''); ?>;
</script>

<script src="js/app.js"></script>

<script
src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode($GOOGLE_KEY); ?>&libraries=places&language=es&region=mx"
async defer></script>

</body>
</html>
