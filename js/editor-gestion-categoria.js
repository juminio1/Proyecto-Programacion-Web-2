(() => {
    const buscador = document.getElementById('buscar-categoria');
    if (!buscador) return;

    const tabla = document.getElementById('tabla-categorias');
    const filas = tabla.querySelectorAll('tbody tr');
    const sinCoincidencias = document.getElementById('categorias-sin-coincidencias');

    function normalizar(texto) {
        return texto.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function filtrar() {
        const busqueda = normalizar(buscador.value);
        let visibles = 0;
        for (const fila of filas) {
            const coincide = normalizar(fila.cells[0].textContent).includes(busqueda);
            fila.hidden = !coincide;
            if (coincide) visibles++;
        }
        tabla.hidden = visibles === 0;
        sinCoincidencias.hidden = visibles > 0;
    }

    document.getElementById('herramientas-categorias').hidden = false;
    buscador.addEventListener('input', filtrar);
    filtrar();

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
