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
                // Respuesta incorrecta
                mensaje.textContent = data.mensaje || 'Respuesta incorrecta. Fin de la partida.';
                boton.textContent = 'Partida finalizada';
                boton.disabled = true;
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