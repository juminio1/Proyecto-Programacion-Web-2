document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('modal-baja-pregunta');
    const botonConfirmar = document.getElementById('confirmar-baja-pregunta');

    let preguntaSeleccionadaParaBaja = null;

    if (!modal || !botonConfirmar) {
        return;
    }


    function abrirConfirmacionBaja(idPregunta) {

        preguntaSeleccionadaParaBaja = idPregunta;

        modal.classList.add('editor-modal-visible');
        modal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('editor-modal-abierto');
    }


    function cerrarConfirmacionBaja() {

        modal.classList.remove('editor-modal-visible');
        modal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('editor-modal-abierto');

        preguntaSeleccionadaParaBaja = null;
    }


    document.querySelectorAll('[data-accion="abrir-baja"]')
        .forEach(function (boton) {

            boton.addEventListener('click', function () {

                const idPregunta = this.dataset.preguntaId;

                abrirConfirmacionBaja(idPregunta);
            });

        });


    document.querySelectorAll('[data-accion="cerrar-baja"]')
        .forEach(function (elemento) {

            elemento.addEventListener('click', function () {
                cerrarConfirmacionBaja();
            });

        });


    botonConfirmar.addEventListener('click', async function () {

        if (preguntaSeleccionadaParaBaja === null) {
            return;
        }

        const idPregunta = preguntaSeleccionadaParaBaja;

        botonConfirmar.disabled = true;
        botonConfirmar.textContent = 'Procesando...';

        const datos = new URLSearchParams();

        datos.append(
            'pregunta_id',
            idPregunta
        );

        try {

            const respuesta = await fetch(
                '/editor/darDeBajaPregunta',
                {
                    method: 'POST',
                    body: datos
                }
            );

            const resultado = await respuesta.json();

            if (!respuesta.ok || !resultado.exito) {

                alert(
                    resultado.mensaje
                    ?? 'No se pudo dar de baja la pregunta.'
                );

                return;
            }

            cerrarConfirmacionBaja();
            window.location.reload();

        } catch (error) {

            console.error(
                'Error al dar de baja la pregunta:',
                error
            );

            alert(
                'Ocurrió un error al comunicarse con el servidor.'
            );

        } finally {

            botonConfirmar.disabled = false;
            botonConfirmar.textContent = 'Dar de baja';
        }

    });


    document.addEventListener(
        'keydown',
        function (evento) {

            if (
                evento.key === 'Escape'
                && modal.classList.contains(
                    'editor-modal-visible'
                )
            ) {
                cerrarConfirmacionBaja();
            }

        }
    );

});