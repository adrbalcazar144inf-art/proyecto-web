const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Función para actualizar el contenido principal del panel (puntos, etc.)
function actualizarPanel() {
    fetch('panel_estudiante.php', { headers: { 'X-CSRF-Token': csrf } })
        .then(res => res.ok ? res.text() : Promise.reject(res))
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Actualizar puntos
            const ptsNuevo = doc.querySelector('.card-neon p.fs-2')?.textContent;
            if (ptsNuevo) document.querySelector('.card-neon p.fs-2').textContent = ptsNuevo;
        })
        .catch(() => console.error('Error al actualizar panel'));
}

// Función para recargar todos los iframes de golpe
function actualizarIframes() {
    document.querySelectorAll('.iframe-container iframe').forEach(iframe => {
        iframe.src = iframe.src;
    });
}

// Recargar panel cada 10 segundos
setInterval(actualizarPanel, 1000);

// Recargar todos los iframes cada 15 segundos
setInterval(actualizarIframes, 1000);

// Enlaces internos vía AJAX
document.querySelectorAll('a[data-route]').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const url = link.getAttribute('href');
        if (url.includes('logout.php')) {
            window.location.href = url;
            return;
        }
        fetch(url, { headers: { 'X-CSRF-Token': csrf } })
            .then(res => res.ok ? res.text() : Promise.reject(res))
            .then(html => document.getElementById('content').innerHTML = html)
            .catch(() => document.getElementById('content').innerHTML = '<p style="color:red">Error al cargar sección</p>');
    });
});
