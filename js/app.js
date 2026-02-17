// ✅ BASE_PATH normalizado: "" o "/algo/" (siempre con slash final)
const RAW_BASE_PATH = (window.__BASE_PATH__ || '').trim();

const BASE_PATH = RAW_BASE_PATH
  ? ('/' + RAW_BASE_PATH.replace(/^\/+|\/+$/g, '') + '/')
  : '/';

// ✅ Endpoints (siempre absolutos desde el dominio)
const ENDPOINT_GUARDAR_TIENDA     = `${BASE_PATH}guardar_tienda.php`;
const ENDPOINT_GUARDAR_PRODUCTOS  = `${BASE_PATH}guardar_producto.php`;
const ENDPOINT_GUARDAR_COMENTARIO = `${BASE_PATH}guardar_comentario_visita.php`;

let tiendaId = window.__TIENDA_ID__ || ''; // viene del PHP
let productos = []; // lista local antes de enviar

/* =======================
   HELPERS DOM
======================= */
const $ = (sel) => document.querySelector(sel);

function val(id) {
  const el = document.getElementById(id);
  return el ? String(el.value ?? '').trim() : '';
}
function setVal(id, v) {
  const el = document.getElementById(id);
  if (el) el.value = v;
}
function getFile(id) {
  const el = document.getElementById(id);
  return el?.files?.length ? el.files[0] : null;
}
function show(el) { if (el) el.style.display = ''; }
function hide(el) { if (el) el.style.display = 'none'; }

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

/* =======================
   ALERTAS
======================= */
function mostrarExito(mensaje, titulo = '¡Éxito!') {
  return Swal.fire({ title: titulo, html: mensaje, icon: 'success', confirmButtonColor: '#27ae60' });
}
function mostrarError(mensaje, titulo = 'Error') {
  return Swal.fire({ title: titulo, html: mensaje, icon: 'error', confirmButtonColor: '#e74c3c' });
}
function mostrarAdvertencia(mensaje, titulo = 'Advertencia') {
  return Swal.fire({ title: titulo, html: mensaje, icon: 'warning', confirmButtonColor: '#f39c12' });
}

function mostrarCargando(mensaje = 'Procesando...') {
  return Swal.fire({
    title: mensaje,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showCancelButton: false,
    showConfirmButton: false, // ✅ loading no debe mostrar botón
    didOpen: () => Swal.showLoading()
  });
}

/* =======================
   FETCH ROBUSTO (JSON vs HTML)
======================= */
async function fetchJsonSeguro(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    ...options
  });

  const contentType = (res.headers.get('content-type') || '').toLowerCase();
  const raw = await res.text();

  console.log('ℹ️', url, 'status:', res.status, 'content-type:', contentType);

  if (!res.ok) {
    console.error('❌ HTTP Error raw:', raw);
    const err = new Error(`HTTP ${res.status}`);
    err.status = res.status;
    err.raw = raw;
    throw err;
  }

  if (!contentType.includes('application/json')) {
    console.error('❌ No es JSON. Raw:', raw);
    const err = new Error('El servidor no devolvió JSON (devolvió HTML u otro formato).');
    err.status = res.status;
    err.raw = raw;
    throw err;
  }

  try {
    return JSON.parse(raw);
  } catch (e) {
    console.error('❌ JSON inválido. Raw:', raw);
    const err = new Error('JSON inválido en la respuesta del servidor.');
    err.status = res.status;
    err.raw = raw;
    throw err;
  }
}

function mostrarErrorBackendConRaw(err, fallbackMsg = 'Ocurrió un error en el servidor.') {
  if (err?.raw) {
    return mostrarError(
      `${fallbackMsg}<br><br>
       <b>Status:</b> ${err.status ?? ''}<br>
       <details style="text-align:left;margin-top:10px">
         <summary>Ver respuesta cruda</summary>
         <pre style="white-space:pre-wrap;max-height:260px;overflow:auto">${escapeHtml(err.raw).slice(0, 5000)}</pre>
       </details>`
    );
  }
  return mostrarError(err?.message || fallbackMsg);
}

/* =======================
   VALIDACIÓN TIENDA (OBLIGATORIOS)
======================= */
function validarTienda() {
  if (!val('nombreTienda')) return { ok: false, msg: 'Falta <b>Nombre de la Tienda</b>.' };
  if (!val('direccion_manual')) return { ok: false, msg: 'Falta <b>Dirección (confirmación manual)</b>.' };

  if (!val('latitud') || !val('longitud')) {
    return { ok: false, msg: 'Falta obtener <b>Ubicación</b> (latitud y longitud).' };
  }

  // ✅ Ahora la foto viene del input de GALERÍA (name="fotoEstanteria")
  const foto = getFile('fotoEstanteriaGaleria');
  if (!foto) return { ok: false, msg: 'La <b>Foto de la Estantería</b> es obligatoria.' };

  const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
  if (foto.type && !allowed.includes(foto.type)) {
    return { ok: false, msg: 'La foto debe ser <b>JPG, PNG, WEBP (o HEIC/HEIF)</b>.' };
  }
  const maxMB = 10;
  if (foto.size > maxMB * 1024 * 1024) {
    return { ok: false, msg: `La foto no puede pesar más de <b>${maxMB}MB</b>.` };
  }

  return { ok: true };
}

/* =======================
   GUARDAR TIENDA
======================= */
async function guardarTiendaYContinuar() {
  const v = validarTienda();
  if (!v.ok) return mostrarAdvertencia(v.msg);

  const fd = new FormData();
  fd.append('nombreTienda', val('nombreTienda'));
  fd.append('direccion_manual', val('direccion_manual'));
  fd.append('direccion', val('direccion'));
  fd.append('latitud', val('latitud'));
  fd.append('longitud', val('longitud'));
  // ✅ foto desde el input de galería (ahí se copia también lo tomado con cámara)
  fd.append('fotoEstanteria', getFile('fotoEstanteriaGaleria'));

  try {
    mostrarCargando('Guardando tienda...');

    const data = await fetchJsonSeguro(ENDPOINT_GUARDAR_TIENDA, {
      method: 'POST',
      body: fd
    });

    Swal.close();

    if (!data?.success) {
      console.error('❌ Backend success=false:', data);
      return mostrarError(data?.message || 'No se pudo guardar la tienda.');
    }

    tiendaId = Number(data?.tiendaId || 0);

    if (!Number.isFinite(tiendaId) || tiendaId <= 0) {
      console.warn('⚠️ No llegó tiendaId válido en la respuesta:', data);
      return mostrarAdvertencia('Se guardó la tienda, pero no llegó un <b>tiendaId</b> válido del servidor.');
    }

    // ✅ guardar en todos lados (sin duplicar)
    window.__TIENDA_ID__ = tiendaId;
    setVal('tiendaId', String(tiendaId));
    console.log('✅ tienda guardada, tiendaId=', tiendaId, 'hidden=', val('tiendaId'), 'resp=', data);

    await mostrarExito(data?.message || 'Tienda guardada. Ahora agrega productos.');

    hide(document.getElementById('formularioTienda'));
    show(document.getElementById('seccionProductos'));

  } catch (err) {
    Swal.close();
    console.error('❌ Guardar tienda (catch):', err);
    return mostrarErrorBackendConRaw(err, 'No se pudo guardar la tienda.');
  }
}

/* =======================
   UBICACIÓN
======================= */
window.obtenerUbicacion = function obtenerUbicacion() {
  if (!navigator.geolocation) {
    mostrarError('Tu navegador no soporta geolocalización.');
    return;
  }

  const cerrarCargando = () => {
    try {
      if (Swal.isVisible() && Swal.getHtmlContainer()) Swal.close();
    } catch (_) {}
  };

  mostrarCargando('Obteniendo ubicación...');

  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      const lat = pos.coords?.latitude;
      const lng = pos.coords?.longitude;

      if (typeof lat !== 'number' || typeof lng !== 'number') {
        cerrarCargando();
        return mostrarError('No se pudo leer latitud/longitud del GPS.');
      }

      setVal('latitud', lat);
      setVal('longitud', lng);

      const fallbackCoords = `GPS: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;

      try {
        if (window.google && window.google.maps && window.google.maps.Geocoder) {
          try {
            const direccion = await reverseGeocodeGoogle(lat, lng);
            if (direccion && direccion.trim()) {
              setVal('direccion', direccion.trim());
            } else {
              setVal('direccion', fallbackCoords);
            }
          } catch (err) {
            console.warn('Geocoder falló, usando coordenadas:', err);
            setVal('direccion', fallbackCoords);
          }
        } else {
          console.warn('Google Maps NO disponible, usando coordenadas');
          setVal('direccion', fallbackCoords);
        }
      } finally {
        cerrarCargando();
      }

      await mostrarExito(
        `Ubicación obtenida.<br>
         <b>Lat:</b> ${lat}<br>
         <b>Lng:</b> ${lng}<br>
         <b>Dirección:</b> ${escapeHtml(val('direccion'))}`,
        'Ubicación lista'
      );
    },
    (err) => {
      cerrarCargando();
      console.error('Geolocation error:', err);
      mostrarError(
        'No se pudo obtener la ubicación.<br>' +
        'Revisa permisos de GPS en tu navegador.'
      );
    },
    {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 0
    }
  );
};

/* =======================
   FOTO UI (GALERÍA + CÁMARA)
   - Botones centrados (en HTML con #fotoBotones)
   - Al cargar foto: botones desaparecen
   - Al quitar foto: botones aparecen
======================= */
function bindFotoUI() {
  const inputGaleria = document.getElementById('fotoEstanteriaGaleria'); // name="fotoEstanteria"
  const inputCamara  = document.getElementById('fotoEstanteriaCamara');  // capture="environment" (sin name)

  const btnGaleria = document.getElementById('btnElegirGaleria');
  const btnCamara  = document.getElementById('btnTomarFoto');

  const contPrev = document.getElementById('vistaPreviaContainer');
  const imgPrev  = document.getElementById('vistaPrevia');
  const btnQuitar = document.getElementById('btnQuitarFoto');

  const fotoBotones = document.getElementById('fotoBotones');

  if (!inputGaleria || !inputCamara || !btnGaleria || !btnCamara) {
    console.warn('⚠️ Foto UI: faltan elementos. Revisa IDs en el HTML.');
    return;
  }

  const hideBotones = () => { if (fotoBotones) fotoBotones.style.display = 'none'; };
  const showBotones = () => { if (fotoBotones) fotoBotones.style.display = 'flex'; };

  function setPreviewFromFile(file) {
    if (!file) return;
    if (imgPrev) imgPrev.src = URL.createObjectURL(file);
    contPrev?.classList.remove('hidden');
    hideBotones();
  }

  function clearFoto() {
    inputGaleria.value = '';
    inputCamara.value = '';
    if (imgPrev) imgPrev.src = '';
    contPrev?.classList.add('hidden');
    showBotones();
  }

  btnGaleria.addEventListener('click', () => inputGaleria.click());
  btnCamara.addEventListener('click', () => inputCamara.click());

  inputGaleria.addEventListener('change', () => {
    const file = inputGaleria.files?.[0] || null;
    if (!file) return clearFoto();
    setPreviewFromFile(file);
  });

  inputCamara.addEventListener('change', () => {
    const file = inputCamara.files?.[0] || null;
    if (!file) return;

    // Mostrar preview
    setPreviewFromFile(file);

    // ✅ Copiar archivo a inputGaleria para que el backend lo reciba por name="fotoEstanteria"
    try {
      const dt = new DataTransfer();
      dt.items.add(file);
      inputGaleria.files = dt.files;
    } catch (e) {
      console.warn('DataTransfer no disponible en este navegador. Si falla el envío, usa FormData manual.', e);
    }
  });

  btnQuitar?.addEventListener('click', clearFoto);

  // Estado inicial: si no hay preview, mostrar botones centrados
  showBotones();
}

/* =======================
   PRODUCTOS (local)
======================= */
function validarProducto() {
  const marca = val('marca');
  const bloom = val('bloom');
  const presentacion = val('presentacion');
  const precio = val('precio');

  if (!tiendaId && !val('tiendaId')) return { ok: false, msg: 'No hay <b>tiendaId</b>. Primero guarda la tienda.' };
  if (!marca) return { ok: false, msg: 'Falta <b>Marca</b>.' };
  if (!bloom) return { ok: false, msg: 'Falta <b>Bloom</b>.' };
  if (!presentacion) return { ok: false, msg: 'Falta <b>Presentación</b>.' };
  if (!precio) return { ok: false, msg: 'Falta <b>Precio</b>.' };

  const p = Number(precio);
  if (!Number.isFinite(p) || p <= 0) return { ok: false, msg: 'El <b>Precio</b> debe ser mayor a 0.' };

  return { ok: true };
}

function renderProductos() {
  const cont = document.getElementById('listaProductos');
  if (!cont) return;

  if (!productos.length) {
    cont.innerHTML = '<div class="text-muted">Aún no hay productos agregados.</div>';
    return;
  }

  cont.innerHTML = productos.map((p, i) => `
    <div class="card mt-10" style="padding:12px">
      <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
        <div>
          <b>${escapeHtml(p.marca)}</b> · Bloom <b>${escapeHtml(p.bloom)}</b> · <b>${escapeHtml(p.presentacion)}</b><br>
          Precio: <b>$${Number(p.precio).toFixed(2)}</b>
        </div>
        <div>
          <button type="button" class="btn btn-danger" data-del="${i}">🗑️ Quitar</button>
        </div>
      </div>
    </div>
  `).join('');

  cont.querySelectorAll('button[data-del]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idx = Number(btn.getAttribute('data-del'));
      productos.splice(idx, 1);
      renderProductos();
    });
  });
}

function limpiarFormProducto() {
  setVal('marca', '');
  setVal('bloom', '');
  setVal('presentacion', '');
  setVal('precio', '');
}

function agregarProductoLocal() {
  const v = validarProducto();
  if (!v.ok) return mostrarAdvertencia(v.msg);

  productos.push({
    marca: val('marca'),
    bloom: val('bloom'),
    presentacion: val('presentacion'),
    precio: Number(val('precio'))
  });

  renderProductos();
  limpiarFormProducto();
}

/* =======================
   FINALIZAR CAPTURA (comentario OBLIGATORIO y bloqueado)
======================= */
window.finalizarCaptura = async function finalizarCaptura() {
  const realTiendaId = Number(val('tiendaId') || tiendaId || window.__TIENDA_ID__ || 0);

  console.log('🔎 finalizarCaptura tiendaId=', {
    realTiendaId,
    hidden: val('tiendaId'),
    global: tiendaId,
    win: window.__TIENDA_ID__
  });

  if (!Number.isFinite(realTiendaId) || realTiendaId <= 0) {
    return mostrarError('No existe tiendaId válido. Primero guarda la tienda.');
  }

  if (!productos.length) return mostrarAdvertencia('Agrega al menos <b>1 producto</b> antes de finalizar.');

  const payload = { tiendaId: realTiendaId, productos };
  console.log('📦 payload a guardar_producto.php =', payload);

  try {
    mostrarCargando('Guardando productos...');

    const data = await fetchJsonSeguro(ENDPOINT_GUARDAR_PRODUCTOS, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!data?.success) {
      Swal.close();
      console.error('❌ guardar_producto success=false:', data);
      return mostrarError(data?.message || 'No se pudieron guardar los productos.');
    }

    Swal.close();

    // ✅ Comentario OBLIGATORIO (no cerrar por fuera/ESC/X)
    const { value: comentario } = await Swal.fire({
      title: 'Agregar comentarios de la visita',
      input: 'textarea',
      inputLabel: 'Obligatorio',
      inputPlaceholder: 'Ej: había promo, no había stock, exhibición distinta, comentarios del encargado...',
      inputAttributes: { 'aria-label': 'Comentarios de la visita' },

      allowOutsideClick: false,
      allowEscapeKey: false,
      showCloseButton: false,
      showCancelButton: false,

      confirmButtonText: 'Guardar y finalizar',
      confirmButtonColor: '#27ae60',

      inputValidator: (value) => {
        if (!value || !value.trim()) return 'El comentario es obligatorio.';
        return null;
      }
    });

    const comentarioFinal = comentario.trim();

    mostrarCargando('Guardando comentario...');

    const resp = await fetchJsonSeguro(ENDPOINT_GUARDAR_COMENTARIO, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        tiendaId: realTiendaId,
        comentario: comentarioFinal
      })
    });

    Swal.close();

    if (!resp?.success) {
      return mostrarError(resp?.message || 'No se pudo guardar el comentario. Intenta de nuevo.');
    }

    await mostrarExito('Captura finalizada correctamente.');

    window.location.href = 'principal.php';

  } catch (err) {
    Swal.close();
    console.error('❌ finalizarCaptura (catch):', err);
    return mostrarErrorBackendConRaw(err, 'No se pudo finalizar la captura.');
  }
};

/* =======================
   INIT / BINDS
======================= */
document.addEventListener('DOMContentLoaded', () => {
  bindFotoUI();
  renderProductos();

  const formTienda = document.getElementById('formTienda');
  formTienda?.addEventListener('submit', (e) => {
    e.preventDefault();
    guardarTiendaYContinuar();
  });

  const formProducto = document.getElementById('formProducto');
  formProducto?.addEventListener('submit', (e) => {
    e.preventDefault();
    agregarProductoLocal();
  });

  const tid = Number(tiendaId || 0);
  if (Number.isFinite(tid) && tid > 0) setVal('tiendaId', String(tid));
});
