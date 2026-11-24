document.addEventListener('DOMContentLoaded', function () {

    const botonImprimir =
        document.getElementById('admin-imprimir-reporte');

    if (!botonImprimir) {
        return;
    }

    botonImprimir.addEventListener('click', function () {
        window.print();
    });

});