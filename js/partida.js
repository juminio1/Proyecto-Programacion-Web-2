(() => {
    const formulario = document.getElementById('partida-formulario');

    if (!formulario) {
        return;
    }

    const boton = formulario.querySelector('.partida-confirmar');
    const mensaje = document.getElementById('partida-mensaje');
    const inputPregunta = document.getElementById('partida-id-pregunta');
    const inputAsignacion = document.getElementById('partida-id-asignacion');
    const elementoPuntaje = document.getElementById('partida-puntaje-valor');
    const elementoAciertos = document.getElementById('partida-aciertos-valor');
    const elementoCategoria = document.querySelector('.partida-categoria');
    const elementoNumero = document.querySelector('.partida-numero');
    const elementoEnunciado = document.getElementById('partida-enunciado');
    const fieldsetOpciones = formulario.querySelector('.partida-opciones');
    const tarjetaPregunta = document.querySelector('.partida-pregunta');
    const pagina = document.querySelector('.partida-pagina');
    const reloj = document.getElementById('partida-reloj');
    const numeroReloj = reloj.querySelector('.partida-reloj-texto strong');
    const unidadReloj = reloj.querySelector('.partida-reloj-texto span');

    const duracionMs = Number(reloj.dataset.duracionMs);
    let vencimiento = 0;
    let intervaloReloj = null;
    let enviando = false;
    let verificandoTiempo = false;
    let partidaFinalizada = false;

    function detenerReloj() {
        clearInterval(intervaloReloj);
        intervaloReloj = null;
    }

    function iniciarReloj(restanteMs, desde = performance.now()) {
        detenerReloj();
        vencimiento = desde + Number(restanteMs);
        intervaloReloj = setInterval(actualizarReloj, 125);
        actualizarReloj();
    }

    function actualizarReloj() {
        const restante = Math.max(0, vencimiento - performance.now());
        const segundos = Math.ceil(restante / 1000);
        const unidad = segundos === 1 ? 'segundo' : 'segundos';

        numeroReloj.textContent = String(segundos);
        unidadReloj.textContent = unidad;
        reloj.setAttribute('aria-label', segundos + ' ' + unidad + (segundos === 1 ? ' restante' : ' restantes'));
        reloj.style.setProperty('--partida-reloj-avance', String(100 - Math.min(100, restante * 100 / duracionMs)));
        reloj.classList.toggle('partida-reloj-urgente', segundos <= 5);

        if (restante === 0) {
            detenerReloj();
            if (!enviando && !verificandoTiempo && !partidaFinalizada) {
                verificarVencimiento();
            }
        }
    }

    function bloquearRespuesta(textoBoton, textoMensaje) {
        fieldsetOpciones.disabled = true;
        boton.disabled = true;
        boton.textContent = textoBoton;
        mensaje.textContent = textoMensaje;
    }

    function habilitarRespuesta(textoMensaje) {
        if (performance.now() >= vencimiento) {
            actualizarReloj();
            return;
        }

        fieldsetOpciones.disabled = false;
        boton.disabled = !formulario.querySelector('input[name="respuesta"]:checked');
        boton.textContent = 'Confirmar respuesta';
        mensaje.textContent = textoMensaje;
    }

    async function verificarVencimiento() {
        verificandoTiempo = true;
        bloquearRespuesta('Verificando tiempo…', 'Estamos verificando el tiempo restante.');

        let restanteMs = null;
        let recibidoEn = null;

        try {
            const datos = new FormData();
            datos.append('id_asignacion', inputAsignacion.value);

            const respuestaHttp = await fetch('/partida/vencer', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: datos
            });
            recibidoEn = performance.now();

            if (respuestaHttp.status === 401) {
                window.location.assign('/login/show');
                return;
            }
            if (respuestaHttp.status === 409) {
                window.location.reload();
                return;
            }
            if (!respuestaHttp.ok) {
                throw new Error('No se pudo comprobar el vencimiento.');
            }

            const data = await respuestaHttp.json();
            if (data.tiempoAgotado) {
                mostrarFinalizacion(data);
                return;
            }

            if (!(data.tiempoRestanteMs > 0) || String(data.idAsignacion) !== inputAsignacion.value) {
                throw new Error('El servidor no devolvió un tiempo válido.');
            }
            restanteMs = data.tiempoRestanteMs;
        } catch (error) {
            console.error('Error al verificar el vencimiento:', error);
            bloquearRespuesta('Tiempo por verificar', 'No pudimos verificar el tiempo. Recargá la página para continuar.');
            return;
        } finally {
            verificandoTiempo = false;
        }

        // Si el aviso llegó antes de tiempo, retomamos el plazo que devolvió PHP.
        iniciarReloj(restanteMs, recibidoEn);
        if (!verificandoTiempo) {
            habilitarRespuesta('Todavía estás a tiempo de confirmar tu respuesta.');
        }
    }

    formulario.addEventListener('change', (evento) => {
        if (evento.target.name === 'respuesta' && !enviando && !verificandoTiempo) {
            habilitarRespuesta('Confirmá tu respuesta cuando estés listo.');
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        if (enviando || verificandoTiempo || partidaFinalizada) {
            return;
        }
        if (performance.now() >= vencimiento) {
            actualizarReloj();
            return;
        }

        const opcion = formulario.querySelector('input[name="respuesta"]:checked');
        if (!opcion) {
            return;
        }

        const formData = new FormData(formulario);
        formData.append('ajax', '1');
        enviando = true;
        bloquearRespuesta('Verificando…', 'Validando tu respuesta…');

        try {
            const respuestaHttp = await fetch(formulario.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });
            const recibidoEn = performance.now();

            if (!respuestaHttp.ok) {
                throw new Error('Error en el servidor al enviar la respuesta.');
            }

            const data = await respuestaHttp.json();
            enviando = false;

            if (data.error) {
                if (data.ya_respondida) {
                    detenerReloj();
                    window.location.reload();
                } else {
                    habilitarRespuesta(data.error);
                }
                return;
            }

            if (data.correcta && data.siguientePregunta) {
                elementoPuntaje.textContent = data.puntaje + (data.puntaje === 1 ? ' punto' : ' puntos');
                elementoAciertos.textContent = String(data.respuestasCorrectas);
                actualizarPregunta(data.siguientePregunta, recibidoEn);
            } else {
                mostrarFinalizacion(data, data.correcta === true);
            }
        } catch (error) {
            console.error('Error al responder:', error);
            enviando = false;
            habilitarRespuesta('Ocurrió un error al procesar tu respuesta. Intentá nuevamente.');
        }
    });

    function actualizarRelojVisible() {
        if (!document.hidden && intervaloReloj !== null) {
            actualizarReloj();
        }
    }

    document.addEventListener('visibilitychange', actualizarRelojVisible);
    window.addEventListener('pageshow', actualizarRelojVisible);

    const navegacion = performance.getEntriesByType('navigation')[0];
    iniciarReloj(reloj.dataset.restanteMs, navegacion?.responseStart || performance.now());

    function mostrarFinalizacion(data, completada = false) {
        partidaFinalizada = true;
        detenerReloj();

        const tiempoAgotado = data.tiempoAgotado === true;
        const puntaje = Number(data.puntaje) || 0;
        const aciertos = Number(data.respuestasCorrectas ?? data.puntaje) || 0;

        // Provisorio: todavía no se mide la duración real.
        const tiempoDemo = '01:30';

        let titulo = 'Partida finalizada';
        let descripcion = 'La partida terminó al responder incorrectamente.';
        let trazoIcono = 'm6 6 12 12M18 6 6 18';
        if (completada) {
            titulo = '¡Partida completada!';
            descripcion = 'Respondiste todas las preguntas disponibles.';
            trazoIcono = 'm5 12 4 4L19 6';
        } else if (tiempoAgotado) {
            titulo = '¡Se te acabó el tiempo!';
            descripcion = 'No confirmaste una respuesta a tiempo. Conservás los puntos que ya ganaste.';
            trazoIcono = 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0ZM12 7v5l3 2';
        }

        const puntosTexto = `${puntaje} ${puntaje === 1 ? 'punto' : 'puntos'}`;

        const icono = `
        <svg viewBox="0 0 24 24"
             width="28"
             height="28"
             fill="none"
             stroke="currentColor"
             stroke-width="2.5"
             stroke-linecap="round"
             stroke-linejoin="round">
            <path d="${trazoIcono}"/>
        </svg>
    `;

        if (pagina) {
            pagina.classList.add('partida-finalizada');
            pagina.classList.toggle('partida-completada', completada);
            pagina.classList.toggle('partida-tiempo-agotado', tiempoAgotado);
        }

        if (elementoPuntaje) {
            elementoPuntaje.textContent = puntosTexto;
        }

        if (elementoAciertos) {
            elementoAciertos.textContent = String(aciertos);
        }

        const encabezado = document.querySelector('.partida-encabezado');

        if (encabezado) {
            encabezado.querySelector('h1').textContent = completada
                ? 'Partida completada'
                : 'Partida finalizada';

            encabezado.querySelector('p').textContent = descripcion;
        }

        let respuestaTarjeta = '';
        let respuestaModal = '';

        if (!completada && data.respuestaCorrecta) {
            const letra = escapeHtml(data.respuestaCorrecta.letra ?? '');
            const texto = escapeHtml(data.respuestaCorrecta.texto ?? '');

            respuestaTarjeta = `
            <div class="partida-fin-correcta">
                <p class="partida-fin-correcta-etiqueta">
                    La respuesta correcta era:
                </p>

                <div class="partida-fin-correcta-opcion">
                    <span class="partida-fin-correcta-letra">
                        ${letra}
                    </span>

                    <span class="partida-fin-correcta-texto">
                        ${texto}
                    </span>
                </div>
            </div>
        `;

            respuestaModal = `
            <div class="partida-modal-correcta">
                <span>La respuesta correcta era:</span>
                <strong>${letra} · ${texto}</strong>
            </div>
        `;
        }

        if (tarjetaPregunta) {
            tarjetaPregunta.classList.remove('animar');
            tarjetaPregunta.style.removeProperty('--color-categoria');

            tarjetaPregunta.setAttribute(
                'aria-labelledby',
                'partida-fin-titulo'
            );

            tarjetaPregunta.innerHTML = `
            <div class="partida-fin">
                <div class="partida-fin-icono" aria-hidden="true">
                    ${icono}
                </div>

                <h2 id="partida-fin-titulo" class="partida-fin-titulo">
                    ${completada || tiempoAgotado ? titulo : '¡Respuesta incorrecta!'}
                </h2>

                <p class="partida-fin-mensaje">
                    ${descripcion}
                </p>

                ${respuestaTarjeta}

                <div class="partida-fin-puntaje-contenedor">
                    <span class="partida-fin-puntaje-etiqueta">
                        Puntaje final:
                    </span>

                    <strong class="partida-fin-puntaje-valor">
                        ${puntosTexto}
                    </strong>
                </div>

                <button type="button"
                        id="partida-ver-estadisticas"
                        class="partida-fin-boton">
                    Ver estadísticas
                </button>

                <a href="/inicio/show" class="partida-fin-enlace">
                    Volver al inicio
                </a>
            </div>
        `;
        }

        document.querySelectorAll(
            '.partida-tiempo, .partida-formulario-abandonar'
        ).forEach((elemento) => {
            elemento.style.display = 'none';
        });

        const tituloResumen = document.getElementById(
            'partida-resumen-titulo'
        );

        if (tituloResumen) {
            tituloResumen.textContent = 'Resultado';
        }

        const formSalida = document.querySelector(
            '.partida-pagina > .partida-formulario-salida'
        );

        if (formSalida) {
            const enlace = document.createElement('a');

            enlace.href = '/inicio/show';
            enlace.className = 'partida-volver';
            enlace.innerHTML = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19 12H5m6-6-6 6 6 6"/>
            </svg>
            Volver al inicio
        `;

            formSalida.replaceWith(enlace);
        }

        // Popup nativo: bloquea la interacción con el fondo mientras está abierto.
        const dialogo = document.createElement('dialog');

        dialogo.className = 'partida-modal'
            + (completada ? ' partida-modal-completada' : '')
            + (tiempoAgotado ? ' partida-modal-tiempo' : '');

        dialogo.setAttribute(
            'aria-labelledby',
            'partida-modal-titulo'
        );

        dialogo.setAttribute(
            'aria-describedby',
            'partida-modal-descripcion'
        );

        dialogo.innerHTML = `
        <button type="button"
                class="partida-modal-cerrar"
                aria-label="Cerrar estadísticas"
                autofocus>
            <span aria-hidden="true">×</span>
        </button>

        <div class="partida-modal-icono" aria-hidden="true">
            ${icono}
        </div>

        <p class="partida-modal-etiqueta">
            RESUMEN DE TU PARTIDA
        </p>

        <h2 id="partida-modal-titulo">
            ${titulo}
        </h2>

        <p id="partida-modal-descripcion"
           class="partida-modal-descripcion">
            ${descripcion}
        </p>

        <div class="partida-modal-estadisticas">
            <div class="partida-modal-dato">
                <span>Puntaje</span>
                <strong>${puntosTexto}</strong>
            </div>

            <div class="partida-modal-dato">
                <span>Aciertos</span>
                <strong>${aciertos}</strong>
            </div>

            ${tiempoAgotado ? '' : `
                <div class="partida-modal-dato">
                    <span>Tiempo (demo)</span>
                    <strong>${tiempoDemo}</strong>
                </div>
            `}
        </div>

        ${respuestaModal}

        <a href="/inicio/show" class="partida-modal-inicio">
            Volver al inicio
        </a>
    `;

        document.body.appendChild(dialogo);

        dialogo.querySelector('.partida-modal-cerrar')
            .addEventListener('click', () => {
                dialogo.close();
            });

        const botonEstadisticas = document.getElementById(
            'partida-ver-estadisticas'
        );

        if (botonEstadisticas) {
            botonEstadisticas.addEventListener('click', () => {
                dialogo.showModal();
            });

            dialogo.addEventListener('close', () => {
                botonEstadisticas.focus();
            });
        }

        dialogo.showModal();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function actualizarPregunta(nuevaPregunta, recibidaEn) {
        if (tarjetaPregunta && nuevaPregunta.color) {
            tarjetaPregunta.style.setProperty('--color-categoria', nuevaPregunta.color);
        }
        if (pagina && nuevaPregunta.color) {
            pagina.style.setProperty('--color-categoria', nuevaPregunta.color);
        }

        if (elementoCategoria && nuevaPregunta.categoria) {
            elementoCategoria.textContent = nuevaPregunta.categoria;
        }

        if (elementoEnunciado) {
            const textoPregunta = nuevaPregunta.pregunta || nuevaPregunta.enunciado;
            if (textoPregunta) {
                elementoEnunciado.textContent = textoPregunta;
            }
        }

        if (tarjetaPregunta) {
            tarjetaPregunta.classList.remove('animar');
            void tarjetaPregunta.offsetWidth;
            tarjetaPregunta.classList.add('animar');
        }

        if (elementoNumero && nuevaPregunta.numeroPregunta) {
            elementoNumero.textContent = `Pregunta ${nuevaPregunta.numeroPregunta}`;
        }
        if (inputPregunta && nuevaPregunta.id) {
            inputPregunta.value = nuevaPregunta.id;
        }
        inputAsignacion.value = nuevaPregunta.idAsignacion;

        // Renderizar opciones nuevas
        if (fieldsetOpciones && Array.isArray(nuevaPregunta.opciones)) {
            const legend = fieldsetOpciones.querySelector('legend');
            fieldsetOpciones.innerHTML = '';
            if (legend) {
                fieldsetOpciones.appendChild(legend);
            }

            nuevaPregunta.opciones.forEach((opcion) => {
                const label = document.createElement('label');
                label.className = 'partida-opcion';

                const input = document.createElement('input');
                input.className = 'partida-radio';
                input.type = 'radio';
                input.name = 'respuesta';
                input.value = opcion.id;
                input.dataset.texto = opcion.texto;
                input.required = true;

                const contenido = document.createElement('span');
                contenido.className = 'partida-opcion-contenido';

                const letraSpan = document.createElement('span');
                letraSpan.className = 'partida-letra';
                letraSpan.setAttribute('aria-hidden', 'true');
                letraSpan.textContent = opcion.letra;

                const textoSpan = document.createElement('span');
                textoSpan.className = 'partida-opcion-texto';
                textoSpan.textContent = opcion.texto;

                contenido.appendChild(letraSpan);
                contenido.appendChild(textoSpan);

                label.appendChild(input);
                label.appendChild(contenido);

                fieldsetOpciones.appendChild(label);
            });
        }

        if (typeof window.reiniciarReportePregunta === 'function') {
            window.reiniciarReportePregunta();
        }

        iniciarReloj(nuevaPregunta.tiempoRestanteMs, recibidaEn);
        if (!verificandoTiempo) {
            habilitarRespuesta('¡Respuesta correcta! +1 punto. Elegí una opción para continuar.');
        }
    }
})();