document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('modal-baja-pregunta');
    const botonConfirmar = document.getElementById('confirmar-baja-pregunta');

    const buscadorPregunta =
        document.getElementById('buscar-pregunta');

    const filtroCategoria =
        document.getElementById('filtrar-categoria');

    const seccionesCategorias =
        document.querySelectorAll('.editor-categoria-preguntas');

    const mensajeSinResultados =
        document.getElementById('editor-sin-resultados');

    let preguntaSeleccionadaParaBaja = null;
    let botonSeleccionadoParaBaja = null;

    if (!modal || !botonConfirmar) {
        return;
    }


    function abrirConfirmacionBaja(idPregunta, boton) {

        preguntaSeleccionadaParaBaja = idPregunta;
        botonSeleccionadoParaBaja = boton;

        modal.classList.add('editor-modal-visible');
        modal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('editor-modal-abierto');
    }


    function cerrarConfirmacionBaja() {

        modal.classList.remove('editor-modal-visible');
        modal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('editor-modal-abierto');

        preguntaSeleccionadaParaBaja = null;
        botonSeleccionadoParaBaja = null;
    }


    document.querySelectorAll('[data-accion="abrir-baja"]')
        .forEach(function (boton) {

            boton.addEventListener('click', function () {

                const idPregunta = this.dataset.preguntaId;

                abrirConfirmacionBaja(
                    idPregunta,
                    this
                );
            });

        });


    document.querySelectorAll('[data-accion="cerrar-baja"]')
        .forEach(function (elemento) {

            elemento.addEventListener('click', function () {
                cerrarConfirmacionBaja();
            });

        });


    botonConfirmar.addEventListener('click', async function () {

        if (
            preguntaSeleccionadaParaBaja === null
            || botonSeleccionadoParaBaja === null
        ) {
            return;
        }

        const idPregunta = preguntaSeleccionadaParaBaja;
        const botonPregunta = botonSeleccionadoParaBaja;

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


            quitarPreguntaDadaDeBajaDelListado(
                botonPregunta
            );

            cerrarConfirmacionBaja();

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


    function quitarPreguntaDadaDeBajaDelListado(botonPregunta) {

        const filaPregunta = botonPregunta.closest('tr');

        if (!filaPregunta) {
            return;
        }

        const seccionCategoria = filaPregunta.closest('.editor-panel');

        filaPregunta.remove();

        if (!seccionCategoria) {
            return;
        }

        const preguntasRestantes =
            seccionCategoria.querySelectorAll('tbody tr').length;

        const contador =
            seccionCategoria.querySelector('.editor-categorias-total');

        if (contador) {
            contador.textContent =
                preguntasRestantes === 1
                    ? '1 pregunta'
                    : preguntasRestantes + ' preguntas';
        }

        if (preguntasRestantes === 0) {
            seccionCategoria.remove();
        }

        aplicarFiltrosPreguntas();
    }

    function normalizarTexto(texto) {

        return texto
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }


    function aplicarFiltrosPreguntas() {

        const textoBuscado = normalizarTexto(
            buscadorPregunta?.value ?? ''
        );

        const categoriaSeleccionada =
            filtroCategoria?.value ?? '';

        let cantidadPreguntasVisibles = 0;


        seccionesCategorias.forEach(function (seccion) {

            const nombreCategoria =
                seccion.dataset.categoria ?? '';

            const coincideCategoria =
                categoriaSeleccionada === ''
                || nombreCategoria === categoriaSeleccionada;

            let preguntasVisiblesEnCategoria = 0;


            seccion
                .querySelectorAll('.editor-fila-pregunta')
                .forEach(function (fila) {

                    const textoPregunta =
                        normalizarTexto(
                            fila.dataset.pregunta ?? ''
                        );

                    const coincideBusqueda =
                        textoBuscado === ''
                        || textoPregunta.includes(textoBuscado);

                    const mostrarPregunta =
                        coincideCategoria
                        && coincideBusqueda;

                    fila.hidden = !mostrarPregunta;

                    if (mostrarPregunta) {
                        preguntasVisiblesEnCategoria++;
                        cantidadPreguntasVisibles++;
                    }

                });


            seccion.hidden =
                preguntasVisiblesEnCategoria === 0;


            const contador =
                seccion.querySelector(
                    '.editor-categorias-total'
                );

            if (contador) {

                contador.textContent =
                    preguntasVisiblesEnCategoria === 1
                        ? '1 pregunta'
                        : preguntasVisiblesEnCategoria
                        + ' preguntas';
            }

        });


        if (mensajeSinResultados) {

            mensajeSinResultados.hidden =
                cantidadPreguntasVisibles > 0;
        }
    }

    if (buscadorPregunta) {

        buscadorPregunta.addEventListener(
            'input',
            aplicarFiltrosPreguntas
        );
    }


    if (filtroCategoria) {

        filtroCategoria.addEventListener(
            'change',
            aplicarFiltrosPreguntas
        );
    }

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