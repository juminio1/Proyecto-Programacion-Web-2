(() => {
    const formulario = document.getElementById('editor-categoria-formulario');
    if (!formulario) {
        return;
    }
    const nombre = document.getElementById('categoria-nombre');
    const color = document.getElementById('categoria-color');
    const etiqueta = document.getElementById('categoria-vista-previa');
    const nombreVistaPrevia = document.getElementById('categoria-vista-previa-nombre');
    const valorColor = document.getElementById('categoria-color-valor');

    function actualizarVistaPrevia() {
        nombreVistaPrevia.textContent = nombre.value.trim() || 'Nombre de la categoría';
        if (/^#[0-9a-f]{6}$/i.test(color.value)) {
            etiqueta.style.setProperty('--categoria-color', color.value);
            valorColor.textContent = color.value.toUpperCase();
        }
    }

    nombre.addEventListener('input', actualizarVistaPrevia);
    color.addEventListener('input', actualizarVistaPrevia);
    actualizarVistaPrevia();
})();
