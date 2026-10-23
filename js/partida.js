(() => {
    const formulario = document.getElementById('partida-formulario');

    if (!formulario) {
        return;
    }

    const boton = formulario.querySelector('.partida-confirmar');
    const mensaje = document.getElementById('partida-mensaje');

    formulario.addEventListener('change', () => {
        const opcion = formulario.querySelector('input[name="respuesta"]:checked');

        boton.disabled = !opcion;
        mensaje.textContent = opcion
            ? 'Confirmá tu respuesta cuando estés listo.'
            : 'Seleccioná una opción para responder.';
    });

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();

        const opcion = formulario.querySelector('input[name="respuesta"]:checked');

        if (!opcion) {
            return;
        }

        // Solo confirma la selección en pantalla.
        // La validación y el guardado se implementarán con el backend.
        mensaje.textContent = 'Respuesta confirmada: ' + opcion.dataset.texto + '.';

        formulario.querySelectorAll('input[name="respuesta"]').forEach((input) => {
            input.disabled = true;
        });

        boton.textContent = 'Respuesta confirmada';
        boton.disabled = true;
    });
})();

