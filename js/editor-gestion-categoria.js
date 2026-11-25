(() => {
    const tabla = document.getElementById('tabla-categorias');
    if (!tabla) return;

    const dialogo = document.getElementById('baja-categoria');
    const nombre = document.getElementById('nombre-categoria-baja');
    const id = document.getElementById('id-categoria-baja');

    tabla.addEventListener('click', evento => {
        const boton = evento.target.closest('button[data-baja-categoria]');
        if (!boton) return;

        nombre.textContent = boton.dataset.nombre;
        id.value = boton.dataset.bajaCategoria;
        dialogo.showModal();
    });
    dialogo.querySelector('.editor-modal-cancelar').addEventListener('click', () => dialogo.close());
})();
