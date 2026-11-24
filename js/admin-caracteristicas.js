// Gráficos de "Estadísticas de los usuarios" (Chart.js). Los datos vienen en data-graficos.
(function () {
    'use strict';

    var contenedor = document.getElementById('admin-caracteristicas');
    if (!contenedor || typeof Chart === 'undefined') {
        return; // Sin Chart.js igual quedan visibles las tablas con los datos.
    }

    var graficos;
    try {
        graficos = JSON.parse(contenedor.getAttribute('data-graficos'));
    } catch (e) {
        return;
    }

    var PALETA = ['#572acf', '#2fa866', '#f0a020', '#e0403b', '#3b8ee0', '#c44fb5', '#7a8599', '#14a3a3', '#a0662d'];
    var instancias = [];

    function estilo(variable, respaldo) {
        var valor = getComputedStyle(document.documentElement).getPropertyValue(variable).trim();
        return valor || respaldo;
    }

    function crear(id, config) {
        var canvas = document.getElementById(id);
        if (canvas) {
            instancias.push(new Chart(canvas, config));
        }
    }

    function dibujar() {
        Chart.defaults.color = estilo('--texto-principal', '#25154d');
        Chart.defaults.font.family = 'Nunito, Arial, sans-serif';
        Chart.defaults.borderColor = estilo('--borde', '#e6ddf4');
        Chart.defaults.maintainAspectRatio = false;

        var barras = {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        };

        crear('grafico-paises', {
            type: 'bar',
            data: {
                labels: graficos.paises.etiquetas,
                datasets: [{ label: 'Usuarios', data: graficos.paises.valores, backgroundColor: PALETA[0] }]
            },
            options: barras
        });

        crear('grafico-sexos', {
            type: 'doughnut',
            data: {
                labels: graficos.sexos.etiquetas,
                datasets: [{ data: graficos.sexos.valores, backgroundColor: [PALETA[4], PALETA[5], PALETA[6]] }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });

        crear('grafico-edades', {
            type: 'bar',
            data: {
                labels: graficos.edades.etiquetas,
                datasets: [{ label: 'Usuarios', data: graficos.edades.valores, backgroundColor: [PALETA[2], PALETA[1], PALETA[7]] }]
            },
            options: barras
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
