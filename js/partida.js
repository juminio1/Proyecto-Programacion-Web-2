(() => {
    const formulario = document.getElementById('partida-formulario');

    if (!formulario) {
        return;
    }

    const boton = formulario.querySelector('.partida-confirmar');
    const mensaje = document.getElementById('partida-mensaje');
    const inputPregunta = document.getElementById('partida-id-pregunta');
    const elementoPuntaje = document.getElementById('partida-puntaje-valor');
    const elementoAciertos = document.getElementById('partida-aciertos-valor');
    const elementoCategoria = document.querySelector('.partida-categoria');
    const elementoNumero = document.querySelector('.partida-numero');
    const elementoEnunciado = document.getElementById('partida-enunciado');
    const fieldsetOpciones = formulario.querySelector('.partida-opciones');
    const tarjetaPregunta = document.querySelector('.partida-pregunta');
    const pagina = document.querySelector('.partida-pagina');

    let enviando = false;

    formulario.addEventListener('change', (evento) => {
        if (evento.target.name === 'respuesta') {
            const opcion = formulario.querySelector('input[name="respuesta"]:checked');
            boton.disabled = !opcion || enviando;
            mensaje.textContent = opcion
                ? 'Confirmá tu respuesta cuando estés listo.'
                : 'Seleccioná una opción para responder.';
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        // Backend/Frontend: Evitar que una respuesta enviada nuevamente vuelva a sumar puntos
        if (enviando) {
            return;
        }

        const opcion = formulario.querySelector('input[name="respuesta"]:checked');
        if (!opcion) {
            return;
        }

        const idPregunta = inputPregunta ? inputPregunta.value : '';
        const respuesta = opcion.value;

        // Deshabilitar controles para evitar doble envío
        enviando = true;
        boton.disabled = true;
        boton.textContent = 'Verificando...';
        mensaje.textContent = 'Validando tu respuesta...';

        formulario.querySelectorAll('input[name="respuesta"]').forEach((input) => {
            input.disabled = true;
        });

        try {
            const formData = new FormData();
            formData.append('id_pregunta', idPregunta);
            formData.append('respuesta', respuesta);
            formData.append('ajax', '1');

            const respuestaHttp = await fetch('/partida/responder', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            });

            if (!respuestaHttp.ok) {
                throw new Error('Error en el servidor al enviar la respuesta.');
            }

            const data = await respuestaHttp.json();

            if (data.error) {
                mensaje.textContent = data.error;

                if (data.ya_respondida) {
                    boton.textContent = 'Ya respondida';
                    boton.disabled = true;
                } else {
                    boton.textContent = 'Confirmar respuesta';
                    boton.disabled = false;
                    formulario.querySelectorAll('input[name="respuesta"]').forEach((input) => {
                        input.disabled = false;
                    });
                    enviando = false;
                }
                return;
            }

            if (data.ya_respondida) {
                mensaje.textContent = data.error || 'Esta pregunta ya fue respondida en esta partida.';
                boton.textContent = 'Ya respondida';
                boton.disabled = true;
                return;
            }

            if (data.correcta) {
                // 1 - Cada respuesta correcta suma exactamente un punto a la partida.
                // 2 - El puntaje actualizado se muestra en pantalla.
                if (elementoPuntaje) {
                    elementoPuntaje.textContent = `${data.puntaje} puntos`;
                }
                if (elementoAciertos) {
                    elementoAciertos.textContent = `${data.respuestasCorrectas}`;
                }

                // 3 - Después de acertar, el jugador recibe otra pregunta.
                if (data.siguientePregunta) {
                    mensaje.textContent = '¡Respuesta correcta! +1 punto. Cargando siguiente pregunta...';

                    setTimeout(() => {
                        actualizarPregunta(data.siguientePregunta);
                        enviando = false;
                    }, 700);
                } else {
                mostrarFinalizacion(data, true);
            }
            } else {
                mostrarFinalizacion(data);
                if (pagina) {
                    pagina.classList.add('partida-finalizada');
                }

            }
        } catch (error) {
            console.error('Error al responder:', error);
            mensaje.textContent = 'Ocurrió un error al procesar tu respuesta. Intentá nuevamente.';
            formulario.querySelectorAll('input[name="respuesta"]').forEach((input) => {
                input.disabled = false;
            });
            boton.disabled = false;
            boton.textContent = 'Confirmar respuesta';
            enviando = false;
        }
    });

    function mostrarFinalizacion(data, completada = false) {
        const puntaje = Number(data.puntaje) || 0;
        const aciertos = Number(data.respuestasCorrectas ?? data.puntaje) || 0;

        // Provisorio: todavía no se mide la duración real.
        const tiempoDemo = '01:30';

        const titulo = completada
            ? '¡Partida completada!'
            : 'Partida finalizada';

        const descripcion = completada
            ? 'Respondiste todas las preguntas disponibles.'
            : 'La partida terminó al responder incorrectamente.';

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
            <path d="${
            completada
                ? 'm5 12 4 4L19 6'
                : 'm6 6 12 12M18 6 6 18'
        }"/>
        </svg>
    `;

        if (pagina) {
            pagina.classList.add('partida-finalizada');
            pagina.classList.toggle('partida-completada', completada);
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
                    ${completada ? titulo : '¡Respuesta incorrecta!'}
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
            + (completada ? ' partida-modal-completada' : '');

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

            <div class="partida-modal-dato">
                <span>Tiempo (demo)</span>
                <strong>${tiempoDemo}</strong>
            </div>
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

    function actualizarPregunta(nuevaPregunta) {
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

        mensaje.textContent = 'Seleccioná una opción para responder.';
        boton.textContent = 'Confirmar respuesta';
        boton.disabled = true;
    }
})();
