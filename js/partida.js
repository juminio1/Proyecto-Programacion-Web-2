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
                // HU-09: Si la partida ya finalizó, mostrar estado final
                if (data.finalizada) {
                    mostrarFinalizacion(data);
                    return;
                }

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
                    mensaje.textContent = '¡Excelente! Has respondido correctamente todas las preguntas disponibles.';
                    boton.textContent = 'Partida completada';
                }
            } else {
                // HU-09: Respuesta incorrecta — mostrar pantalla de finalización
                mostrarFinalizacion(data);
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

    /**
     * HU-09: Muestra la pantalla de finalización por respuesta incorrecta.
     * Reemplaza el contenido de la tarjeta de pregunta con el resultado final.
     */
    function mostrarFinalizacion(data) {
        if (elementoPuntaje) {
            elementoPuntaje.textContent = `${data.puntaje} puntos`;
        }
        if (elementoAciertos) {
            elementoAciertos.textContent = `${data.respuestasCorrectas}`;
        }

        // Cambiar el encabezado de la página
        const encabezado = document.querySelector('.partida-encabezado');
        if (encabezado) {
            encabezado.querySelector('h1').textContent = 'Partida finalizada';
            encabezado.querySelector('p').textContent = 'Respondiste incorrectamente.';
        }

        // Reemplazar el contenido de la tarjeta de pregunta con el resultado
        if (tarjetaPregunta) {
            tarjetaPregunta.classList.remove('animar');
            tarjetaPregunta.style.setProperty('--color-categoria', '#e0403b');

            let htmlResultado = '<div class="partida-fin">';
            htmlResultado += '<div class="partida-fin-icono" aria-hidden="true">';
            htmlResultado += '<svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>';
            htmlResultado += '</div>';
            htmlResultado += '<h2 class="partida-fin-titulo">¡Respuesta incorrecta!</h2>';
            htmlResultado += '<p class="partida-fin-mensaje">';
            htmlResultado += data.mensaje || 'La partida ha finalizado.';
            htmlResultado += '</p>';

            if (data.respuestaCorrecta) {
                htmlResultado += '<div class="partida-fin-correcta">';
                htmlResultado += '<p class="partida-fin-correcta-etiqueta">La respuesta correcta era:</p>';
                htmlResultado += '<div class="partida-fin-correcta-opcion">';
                htmlResultado += '<span class="partida-letra">' + escapeHtml(data.respuestaCorrecta.letra) + '</span>';
                htmlResultado += '<span class="partida-opcion-texto">' + escapeHtml(data.respuestaCorrecta.texto) + '</span>';
                htmlResultado += '</div>';
                htmlResultado += '</div>';
            }

            htmlResultado += '<p class="partida-fin-puntaje">Puntaje final: <strong>' + data.puntaje + ' punto' + (data.puntaje !== 1 ? 's' : '') + '</strong></p>';
            htmlResultado += '<a href="/inicio/show" class="partida-fin-volver">';
            htmlResultado += '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12l2-2m-2 2l2 2m-2-2h18"/></svg>';
            htmlResultado += 'Volver al inicio';
            htmlResultado += '</a>';
            htmlResultado += '</div>';

            tarjetaPregunta.innerHTML = htmlResultado;
        }

        // Ocultar el botón de abandonar (la partida ya finalizó)
        const formAbandonar = document.querySelector('.partida-formulario-abandonar');
        if (formAbandonar) {
            formAbandonar.style.display = 'none';
        }

        // Ocultar el reloj
        const tiempo = document.querySelector('.partida-tiempo');
        if (tiempo) {
            tiempo.style.display = 'none';
        }

        // Cambiar título del resumen
        const tituloResumen = document.getElementById('partida-resumen-titulo');
        if (tituloResumen) {
            tituloResumen.textContent = 'Resultado';
        }

        // Cambiar el botón superior de "Volver" para que sea un enlace directo, no POST a abandonar
        const formSalida = document.querySelector('.partida-pagina > .partida-formulario-salida');
        if (formSalida) {
            const enlace = document.createElement('a');
            enlace.href = '/inicio/show';
            enlace.className = 'partida-volver';
            enlace.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg> Volver al inicio';
            formSalida.replaceWith(enlace);
        }
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
