// Gráficos del panel de administrador (Chart.js). Los datos vienen en data-graficos.
(function () {
    'use strict';

    var contenedor = document.getElementById('admin-estadisticas');
    if (!contenedor || typeof Chart === 'undefined') {
        return; // Sin Chart.js igual quedan visibles las tablas con los datos.
    }

    var graficos;
    try {
        graficos = JSON.parse(contenedor.getAttribute('data-graficos'));
    } catch (e) {
        return;
    }

    var PALETA = ['#572acf', '#2fa866', '#f0a020', '#e0403b', '#3b8ee0', '#c44fb5'];
    var instancias = [];

    function estilo(variable, respaldo) {
        var valor = getComputedStyle(document.documentElement).getPropertyValue(variable).trim();
        return valor || respaldo;
    }

    function colores() {
        return {
            texto: estilo('--texto-principal', '#25154d'),
            grilla: estilo('--borde', '#e6ddf4')
        };
    }

    function crear(id, config) {
        var canvas = document.getElementById(id);
        if (canvas) {
            instancias.push(new Chart(canvas, config));
        }
    }

    function configurar() {
        var c = colores();
        Chart.defaults.color = c.texto;
        Chart.defaults.font.family = 'Nunito, Arial, sans-serif';
        Chart.defaults.borderColor = c.grilla;
        Chart.defaults.maintainAspectRatio = false;
    }

    function dibujar() {
        configurar();
        var opcionesEjes = {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        };

        crear('grafico-jugadores', {
            type: 'doughnut',
            data: {
                labels: graficos.jugadores.etiquetas,
                datasets: [{ data: graficos.jugadores.valores, backgroundColor: [PALETA[1], PALETA[2]] }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });

        crear('grafico-partidas', {
            type: 'bar',
            data: {
                labels: graficos.partidas.etiquetas,
                datasets: [{ data: graficos.partidas.valores, backgroundColor: [PALETA[1], PALETA[3], PALETA[2]] }]
            },
            options: opcionesEjes
        });

        crear('grafico-preguntas', {
            type: 'bar',
            data: {
                labels: graficos.preguntas.etiquetas,
                datasets: [{ label: 'Disponibles', data: graficos.preguntas.valores, backgroundColor: PALETA[0] }]
            },
            options: opcionesEjes
        });

        crear('grafico-nuevos', {
            type: 'line',
            data: {
                labels: graficos.nuevos.etiquetas,
                datasets: [{
                    label: 'Usuarios nuevos',
                    data: graficos.nuevos.valores,
                    borderColor: PALETA[0],
                    backgroundColor: 'rgba(87, 42, 207, 0.15)',
                    fill: true,
                    tension: 0.25
                }]
            },
            options: opcionesEjes
        });
    }

    function redibujar() {
        instancias.forEach(function (g) { g.destroy(); });
        instancias = [];
        dibujar();
    }

    dibujar();

    // Si se cambia entre modo claro y oscuro, se redibuja con los colores nuevos.
    new MutationObserver(redibujar).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
})();
