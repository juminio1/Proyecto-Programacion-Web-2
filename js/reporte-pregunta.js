(function () {
    const botonAbrir = document.getElementById('partida-reporte-abrir');
    const formulario = document.getElementById('partida-reporte-formulario');
    const botonCancelar = document.getElementById('partida-reporte-cancelar');
    const mensaje = document.getElementById('partida-reporte-mensaje');
    const campoPregunta = document.getElementById('partida-id-pregunta');

    if (!botonAbrir || !formulario || !mensaje || !campoPregunta) {
        return;
    }

    function mostrarMensaje(texto, esExito) {
        mensaje.textContent = texto;
        mensaje.classList.toggle('es-exito', esExito);
        mensaje.classList.toggle('es-error', !esExito);
        mensaje.hidden = false;
    }

    function abrirFormulario() {
        formulario.hidden = false;
        botonAbrir.hidden = true;
        botonAbrir.setAttribute('aria-expanded', 'true');
        document.getElementById('partida-reporte-motivo').focus();
    }

    function cerrarFormulario() {
        formulario.hidden = true;
        botonAbrir.hidden = false;
        botonAbrir.setAttribute('aria-expanded', 'false');
    }

    // Lo puede llamar partida.js cuando se carga la siguiente pregunta
    window.reiniciarReportePregunta = function () {
        formulario.reset();
        formulario.hidden = true;
        mensaje.hidden = true;
        botonAbrir.hidden = false;
        botonAbrir.setAttribute('aria-expanded', 'false');
        formulario.querySelector('button[type="submit"]').disabled = false;
    };

    botonAbrir.addEventListener('click', abrirFormulario);
    botonCancelar.addEventListener('click', cerrarFormulario);

    formulario.addEventListener('submit', async function (evento) {
        evento.preventDefault();

        const botonEnviar = formulario.querySelector('button[type="submit"]');
        botonEnviar.disabled = true;

        const datos = new FormData(formulario);
        datos.set('id_pregunta', campoPregunta.value);
        datos.set('ajax', '1');

        try {
            const respuesta = await fetch(formulario.action, {
                method: 'POST',
                body: datos,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const resultado = await respuesta.json();

            mostrarMensaje(resultado.mensaje || 'No pudimos registrar el reporte.', Boolean(resultado.ok));

            if (resultado.ok) {
                formulario.hidden = true;
                botonAbrir.hidden = true;
            } else {
                botonEnviar.disabled = false;
            }
        } catch (error) {
            mostrarMensaje('Error de conexión. Intentá nuevamente.', false);
            botonEnviar.disabled = false;
        }
    });
})();