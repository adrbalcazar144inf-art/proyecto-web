// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones de teclas
document.addEventListener('keydown', function(e) {
  // Tecla en mayúscula o minúscula, convertimos a mayúscula para comparar
  const key = e.key.toUpperCase();

  if (
    e.key === 'F12' || // F12
    (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(key)) || // Ctrl+Shift+I/J/C
    (e.ctrlKey && key === 'U') // Ctrl+U
  ) {
    e.preventDefault();
    alert('🚫 Acción no permitida');
  }
});
