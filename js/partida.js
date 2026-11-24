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
    const dialogoAbandono = document.getElementById('partida-confirmar-abandono');
    const reloj = document.getElementById('partida-reloj');
    const numeroReloj = reloj.querySelector('.partida-reloj-texto strong');
    const unidadReloj = reloj.querySelector('.partida-reloj-texto span');

    const duracionMs = Number(reloj.dataset.duracionMs);
    let vencimiento = 0;
    let intervaloReloj = null;
    let enviando = false;
    let verificandoTiempo = false;
    let partidaFinalizada = false;
    let formularioAbandono = null;
    let enviandoAbandono = false;
    let consultandoEstado = false;
    let intervaloEstado = null;

    const idPartida = pagina?.dataset.idPartida;
    const navegacion = performance.getEntriesByType('navigation')[0];
    const esRecarga = (navegacion?.type === 'reload');
    let idPestana = null;
    let canalPestanas = null;
    const pestanasConectadas = new Set();
    const temporizadoresDescargaPestanas = new Map();
    const TIEMPO_GRACIA_RECARGA_MS = 2500;

    function obtenerClaveOpcionSessionStorage(idPartidaActual, idPreguntaActual) {
        return `partida_${idPartidaActual}_pregunta_${idPreguntaActual}_opcion`;
    }

    function guardarOpcionSeleccionadaEnSessionStorage(idPartidaActual, idPreguntaActual, idOpcion) {
        if (!idPartidaActual || !idPreguntaActual || !idOpcion) return;
        try {
            sessionStorage.setItem(
                obtenerClaveOpcionSessionStorage(idPartidaActual, idPreguntaActual),
                String(idOpcion)
            );
        } catch (error) {
            console.warn('No se pudo guardar la opción en sessionStorage:', error);
        }
    }

    function restaurarOpcionSeleccionadaDesdeSessionStorage(idPartidaActual, idPreguntaActual) {
        if (!idPartidaActual || !idPreguntaActual || !formulario) return;
        try {
            const clave = obtenerClaveOpcionSessionStorage(idPartidaActual, idPreguntaActual);
            const opcionGuardada = sessionStorage.getItem(clave);
            if (!opcionGuardada) return;

            const radio = formulario.querySelector(
                `input[name="respuesta"][value="${CSS.escape(opcionGuardada)}"]`
            );
            if (radio && !radio.checked) {
                radio.checked = true;
                habilitarRespuesta('Confirmá tu respuesta cuando estés listo.');
            }
        } catch (error) {
            console.warn('No se pudo restaurar la opción de sessionStorage:', error);
        }
    }

    function limpiarOpcionSeleccionadaEnSessionStorage(idPartidaActual, idPreguntaActual) {
        if (!idPartidaActual || !idPreguntaActual) return;
        try {
            sessionStorage.removeItem(obtenerClaveOpcionSessionStorage(idPartidaActual, idPreguntaActual));
        } catch (error) {
            console.warn('No se pudo limpiar la opción de sessionStorage:', error);
        }
    }

    function obtenerOEstablecerIdPestana() {
        let idGuardado = sessionStorage.getItem('partida_id_pestana');
        if (!esRecarga || !idGuardado) {
            idGuardado = 'pes_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 9);
            sessionStorage.setItem('partida_id_pestana', idGuardado);
        }
        return idGuardado;
    }

    async function registrarPestanaEnServidor(esRecargaActual) {
        if (!idPartida || !idPestana) return;

        try {
            const datos = new FormData();
            datos.append('id_partida', idPartida);
            datos.append('id_pestana', idPestana);
            if (esRecargaActual) {
                datos.append('es_recarga', '1');
            }

            const respuesta = await fetch('/partida/registrarPestana', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: datos
            });

            if (respuesta.status === 401) {
                window.location.assign('/login/show');
                return;
            }

            if (!respuesta.ok) return;

            const resultado = await respuesta.json();
            if (resultado.estado === 'abandonada' || resultado.tiempoAgotado) {
                mostrarFinalizacion(resultado);
            }
        } catch (error) {
            console.warn('No se pudo registrar la pestaña en el servidor:', error);
        }
    }

    function notificarDesconexionPestanaAlServidor() {
        if (!idPartida || !idPestana || partidaFinalizada) return;

        try {
            const datos = new FormData();
            datos.append('id_partida', idPartida);
            datos.append('id_pestana', idPestana);
            navigator.sendBeacon('/partida/desconectarPestana', datos);
        } catch (error) {
            console.warn('No se pudo enviar la desconexión con sendBeacon:', error);
        }
    }

    async function solicitarAbandonoPorCierreMultipestana() {
        if (partidaFinalizada || enviandoAbandono) return;
        enviandoAbandono = true;

        try {
            const datos = new FormData();
            datos.append('id_partida', idPartida);
            datos.append('ajax', '1');
            datos.append('motivo', 'cierre_multipestana');

            const respuesta = await fetch('/partida/abandonar', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: datos
            });

            if (respuesta.status === 401) {
                window.location.assign('/login/show');
                return;
            }

            const resultado = await respuesta.json();
            mostrarFinalizacion({
                ...resultado,
                estado: 'abandonada',
                mensaje: 'Partida abandonada: cerraste una pestaña mientras otra seguía conectada.'
            });
        } catch (error) {
            console.error('Error al solicitar abandono por cierre de pestaña:', error);
        } finally {
            enviandoAbandono = false;
        }
    }

    function iniciarCoordinacionPestanas() {
        if (!idPartida) return;

        idPestana = obtenerOEstablecerIdPestana();
        pestanasConectadas.add(idPestana);

        if (typeof BroadcastChannel !== 'undefined') {
            try {
                canalPestanas = new BroadcastChannel('partida_pestanas_' + idPartida);

                canalPestanas.onmessage = (evento) => {
                    const mensajeCanal = evento.data;
                    if (!mensajeCanal || typeof mensajeCanal !== 'object') return;

                    const idPestanaRemota = mensajeCanal.idPestana;
                    if (!idPestanaRemota || idPestanaRemota === idPestana) return;

                    switch (mensajeCanal.tipo) {
                        case 'pestana_conectada':
                            pestanasConectadas.add(idPestanaRemota);
                            canalPestanas.postMessage({
                                tipo: 'pestana_presente',
                                idPestana: idPestana
                            });
                            if (temporizadoresDescargaPestanas.has(idPestanaRemota)) {
                                clearTimeout(temporizadoresDescargaPestanas.get(idPestanaRemota));
                                temporizadoresDescargaPestanas.delete(idPestanaRemota);
                            }
                            break;

                        case 'pestana_presente':
                            pestanasConectadas.add(idPestanaRemota);
                            break;

                        case 'pestana_recargada':
                            // Una recarga no cuenta como cierre
                            if (temporizadoresDescargaPestanas.has(idPestanaRemota)) {
                                clearTimeout(temporizadoresDescargaPestanas.get(idPestanaRemota));
                                temporizadoresDescargaPestanas.delete(idPestanaRemota);
                            }
                            pestanasConectadas.add(idPestanaRemota);
                            break;

                        case 'pestana_descargando':
                            // La otra pestaña comenzó a descargarse
                            if (temporizadoresDescargaPestanas.has(idPestanaRemota)) {
                                clearTimeout(temporizadoresDescargaPestanas.get(idPestanaRemota));
                            }

                            const temporizador = setTimeout(async () => {
                                temporizadoresDescargaPestanas.delete(idPestanaRemota);
                                pestanasConectadas.delete(idPestanaRemota);

                                // Si pasaron los 2.5s y esta pestaña sigue conectada:
                                // "Si se cierra una y otra sigue conectada, solicitar el abandono."
                                if (!partidaFinalizada) {
                                    await solicitarAbandonoPorCierreMultipestana();
                                }
                            }, TIEMPO_GRACIA_RECARGA_MS);

                            temporizadoresDescargaPestanas.set(idPestanaRemota, temporizador);
                            break;

                        case 'partida_abandonada':
                            mostrarFinalizacion(mensajeCanal.datos);
                            break;
                    }
                };

                if (esRecarga) {
                    canalPestanas.postMessage({
                        tipo: 'pestana_recargada',
                        idPestana: idPestana
                    });
                }

                canalPestanas.postMessage({
                    tipo: 'pestana_conectada',
                    idPestana: idPestana,
                    esRecarga: esRecarga
                });
            } catch (error) {
                console.warn('No se pudo inicializar BroadcastChannel:', error);
            }
        }

        registrarPestanaEnServidor(esRecarga);

        window.addEventListener('pagehide', () => {
            if (partidaFinalizada) return;

            canalPestanas?.postMessage({
                tipo: 'pestana_descargando',
                idPestana: idPestana
            });

            notificarDesconexionPestanaAlServidor();
        });
    }

    const ESPERA_MAXIMA_REPORTE_MS = 90000;
    const formularioReporte = document.getElementById('partida-reporte-formulario');
    const mensajeReporte = document.getElementById('partida-reporte-mensaje');
    let esperandoReporte = false;
    let temporizadorEsperaReporte = null;
    let observadorReporte = null;

    function reporteAbierto() {
        return Boolean(formularioReporte) && !formularioReporte.hidden;
    }

    function esperarCierreReporte() {
        esperandoReporte = true;
        bloquearRespuesta(
            'Tiempo agotado',
            'Se acabó el tiempo. Terminá tu reporte y después vas a ver el resultado.'
        );

        const terminar = () => {
            if (!esperandoReporte) return;
            esperandoReporte = false;
            clearTimeout(temporizadorEsperaReporte);
            observadorReporte.disconnect();
            verificarVencimiento();
        };

        observadorReporte = new MutationObserver(() => {
            if (reporteAbierto()) return;
            const enviado = !mensajeReporte.hidden && mensajeReporte.classList.contains('es-exito');
            setTimeout(terminar, enviado ? 2500 : 300);
        });
        observadorReporte.observe(formularioReporte, {
            attributes: true,
            attributeFilter: ['hidden']
        });

        temporizadorEsperaReporte = setTimeout(terminar, ESPERA_MAXIMA_REPORTE_MS);
    }

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
            if (!enviando && !verificandoTiempo && !partidaFinalizada && !esperandoReporte) {
                if (reporteAbierto()) {
                    esperarCierreReporte();
                } else {
                    verificarVencimiento();
                }
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
        if (partidaFinalizada || esperandoReporte) {
            return;
        }

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
            if (data.tiempoAgotado || data.estado === 'abandonada') {
                mostrarFinalizacion(data);
                return;
            }

            if (partidaFinalizada) return;

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
            guardarOpcionSeleccionadaEnSessionStorage(idPartida, inputPregunta?.value, evento.target.value);
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

            if (partidaFinalizada) return;

            limpiarOpcionSeleccionadaEnSessionStorage(idPartida, inputPregunta?.value);

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

    document.querySelectorAll('.partida-formulario-salida').forEach((salida) => {
        salida.addEventListener('submit', (evento) => {
            if (!dialogoAbandono || typeof dialogoAbandono.showModal !== 'function') {
                if (!window.confirm('¿Abandonar la partida? Quedará con 0 puntos.')) evento.preventDefault();
                return;
            }
            evento.preventDefault();
            if (partidaFinalizada || enviandoAbandono || dialogoAbandono.open) return;

            formularioAbandono = salida;
            dialogoAbandono.returnValue = '';
            dialogoAbandono.showModal();
        });
    });

    dialogoAbandono?.addEventListener('close', async () => {
        const salida = formularioAbandono;
        formularioAbandono = null;
        if (!salida || dialogoAbandono.returnValue !== 'abandonar' || partidaFinalizada) return;

        enviandoAbandono = true;
        try {
            const respuesta = await fetch(salida.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(salida)
            });
            if (respuesta.status === 401) {
                window.location.assign('/login/show');
                return;
            }
            const resultado = await respuesta.json();
            if (resultado.estado === 'abandonada' || resultado.tiempoAgotado) {
                mostrarFinalizacion(resultado);
            } else if (!partidaFinalizada) {
                mensaje.textContent = resultado.error || 'No se pudo abandonar la partida.';
            }
        } catch {
            if (!partidaFinalizada) mensaje.textContent = 'No se pudo abandonar la partida. Intentá nuevamente.';
        } finally {
            enviandoAbandono = false;
        }
    });

    async function consultarAbandono() {
        if (!idPartida || partidaFinalizada || consultandoEstado || document.hidden) return;

        consultandoEstado = true;
        try {
            const url = '/partida/estado?id_partida=' + encodeURIComponent(idPartida) + (idPestana ? '&id_pestana=' + encodeURIComponent(idPestana) : '');
            const respuesta = await fetch(url, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            if (!respuesta.ok) return;
            const resultado = await respuesta.json();
            if (resultado.estado === 'abandonada' || resultado.tiempoAgotado) mostrarFinalizacion(resultado);
        } catch {
            // Se vuelve a consultar al recuperar conexión o en el siguiente intervalo.
        } finally {
            consultandoEstado = false;
        }
    }

    if (pagina?.dataset.idPartida) {
        intervaloEstado = setInterval(consultarAbandono, 3000);
        window.addEventListener('pageshow', consultarAbandono);
        window.addEventListener('online', consultarAbandono);
        document.addEventListener('visibilitychange', consultarAbandono);
    }

    iniciarCoordinacionPestanas();
    iniciarReloj(reloj.dataset.restanteMs, navegacion?.responseStart || performance.now());
    restaurarOpcionSeleccionadaDesdeSessionStorage(idPartida, inputPregunta?.value);

    function formatearDuracion(segundos) {
        if (!Number.isInteger(segundos) || segundos < 0) {
            return '—';
        }

        const minutos = String(Math.floor(segundos / 60)).padStart(2, '0');
        const resto = String(segundos % 60).padStart(2, '0');
        return `${minutos}:${resto}`;
    }

    function mostrarFinalizacion(data, completada = false) {
        if (partidaFinalizada) return;
        partidaFinalizada = true;
        detenerReloj();
        clearInterval(intervaloEstado);
        formularioAbandono = null;
        if (dialogoAbandono?.open) dialogoAbandono.close();

        limpiarOpcionSeleccionadaEnSessionStorage(idPartida, inputPregunta?.value);
        temporizadoresDescargaPestanas.forEach((t) => clearTimeout(t));
        temporizadoresDescargaPestanas.clear();
        try {
            canalPestanas?.close();
        } catch {}

        const abandonada = data.estado === 'abandonada';
        const tiempoAgotado = data.tiempoAgotado === true;
        const puntaje = Number(data.puntaje) || 0;
        const aciertos = Number(data.respuestasCorrectas ?? data.puntaje) || 0;

        const tiempoTotal = formatearDuracion(data.duracionSegundos);

        let titulo = 'Partida finalizada';
        let descripcion = 'La partida terminó al responder incorrectamente.';
        let trazoIcono = 'm6 6 12 12M18 6 6 18';
        if (completada) {
            titulo = '¡Partida completada!';
            descripcion = 'Respondiste todas las preguntas disponibles.';
            trazoIcono = 'm5 12 4 4L19 6';
        } else if (abandonada) {
            titulo = 'Partida abandonada';
            descripcion = data.mensaje || 'Esta partida no suma puntos.';
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
            if (abandonada) {
                elementoAciertos.closest('.partida-dato').style.display = 'none';
            } else {
                elementoAciertos.textContent = String(aciertos);
            }
        }

        const encabezado = document.querySelector('.partida-encabezado');

        if (encabezado) {
            encabezado.querySelector('h1').textContent = abandonada
                ? titulo
                : (completada ? 'Partida completada' : 'Partida finalizada');

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
                    ${completada || tiempoAgotado || abandonada ? titulo : '¡Respuesta incorrecta!'}
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
        if (abandonada) dialogo.style.setProperty('--partida-modal-columnas', '1');

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

            ${abandonada ? '' : `
                <div class="partida-modal-dato">
                    <span>Aciertos</span>
                    <strong>${aciertos}</strong>
                </div>
            `}

            ${tiempoAgotado || abandonada ? '' : `
                <div class="partida-modal-dato">
                    <span>Tiempo total</span>
                    <strong>${tiempoTotal}</strong>
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
        limpiarOpcionSeleccionadaEnSessionStorage(idPartida, inputPregunta?.value);

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