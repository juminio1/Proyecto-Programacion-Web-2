/**
 * MODO OSCURO - PREGUNTADOS
 * Lógica para alternar y recordar el tema claro/oscuro
 */
(() => {
    const CLAVE_STORAGE = 'tema';
    const TEMA_OSCURO = 'oscuro';
    const TEMA_CLARO = 'claro';

    function obtenerPreferenciaSistema() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
            ? TEMA_OSCURO
            : TEMA_CLARO;
    }

    function obtenerTemaActual() {
        const guardado = localStorage.getItem(CLAVE_STORAGE);
        if (guardado === TEMA_OSCURO || guardado === TEMA_CLARO) {
            return guardado;
        }
        return obtenerPreferenciaSistema();
    }

    function aplicarTema(tema, guardarEnStorage = true) {
        const esOscuro = tema === TEMA_OSCURO;
        const html = document.documentElement;

        if (esOscuro) {
            html.setAttribute('data-theme', 'dark');
            html.classList.add('dark-mode');
        } else {
            html.setAttribute('data-theme', 'light');
            html.classList.remove('dark-mode');
        }

        if (guardarEnStorage) {
            try {
                localStorage.setItem(CLAVE_STORAGE, tema);
            } catch (e) {
                console.warn('No se pudo guardar la preferencia de tema en localStorage', e);
            }
        }

        actualizarBoton(esOscuro);

        // Disparar evento personalizado por si algún componente (ej. Leaflet) necesita reaccionar
        window.dispatchEvent(new CustomEvent('temaCambiado', {
            detail: { tema, esOscuro }
        }));
    }

    function actualizarBoton(esOscuro) {
        const boton = document.getElementById('boton-modo-oscuro');
        if (!boton) return;

        const textoBoton = boton.querySelector('.boton-tema-texto');
        const proximoTema = esOscuro ? 'claro' : 'oscuro';
        const etiqueta = `Cambiar a modo ${proximoTema}`;

        boton.setAttribute('aria-label', etiqueta);
        boton.setAttribute('title', etiqueta);
        boton.setAttribute('aria-checked', String(esOscuro));

        if (textoBoton) {
            textoBoton.textContent = esOscuro ? 'Modo claro' : 'Modo oscuro';
        }
    }

    function inicializar() {
        const tema = obtenerTemaActual();
        aplicarTema(tema, false);

        const boton = document.getElementById('boton-modo-oscuro');
        if (boton) {
            boton.addEventListener('click', () => {
                const temaActual = document.documentElement.getAttribute('data-theme') === 'dark'
                    ? TEMA_OSCURO
                    : TEMA_CLARO;
                const nuevoTema = temaActual === TEMA_OSCURO ? TEMA_CLARO : TEMA_OSCURO;
                aplicarTema(nuevoTema, true);
            });
        }

        // Si el usuario no eligió un tema explícito, escuchar cambios del sistema
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', (e) => {
                if (!localStorage.getItem(CLAVE_STORAGE)) {
                    aplicarTema(e.matches ? TEMA_OSCURO : TEMA_CLARO, false);
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
})();
