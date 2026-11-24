(function () {
    'use strict';

    const formulario = document.querySelector('[data-filtro-periodo]');
    if (!formulario) return;

    const periodo = document.getElementById('filtro-periodo');
    const fecha = document.getElementById('filtro-fecha');
    const anio = document.getElementById('filtro-anio');
    const mes = document.getElementById('filtro-mes');
    const dia = document.getElementById('filtro-dia');
    const detalle = document.getElementById('filtro-detalle');
    const semana = document.getElementById('filtro-rango-semana');
    const avisoPendiente = document.getElementById('filtro-periodo-pendiente');
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const formatoFecha = new Intl.DateTimeFormat('es-AR', {
        timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric'
    });
    const dosDigitos = numero => String(numero).padStart(2, '0');

    function mostrarCampo(campo, visible) {
        campo.closest('.admin-filtro-campo').hidden = !visible;
        campo.disabled = !visible;
    }

    function cargarFecha(valor, precision) {
        const [anioInicial, mesInicial, diaInicial] = valor.split('-');
        anio.value = anioInicial;
        mes.value = periodo.value === 'anio' && !precision ? '' : mesInicial;
        cargarDias();
        dia.value = precision === 'dia' ? diaInicial : '';
    }

    function cargarDias() {
        const anterior = dia.value;
        const cantidad = new Date(Date.UTC(Number(anio.value), Number(mes.value), 0)).getUTCDate();
        dia.replaceChildren(new Option('Mes completo', ''));
        for (let numero = 1; numero <= cantidad; numero++) {
            dia.add(new Option(String(numero), dosDigitos(numero)));
        }
        if (Number(anterior) <= cantidad) dia.value = anterior;
        if (dia.selectedIndex < 0) dia.value = '';
    }

    function actualizarSemana() {
        const fechaElegida = new Date(fecha.value + 'T00:00:00Z');
        semana.hidden = periodo.value !== 'semana' || Number.isNaN(fechaElegida.getTime());
        if (semana.hidden) {
            semana.textContent = '';
            return;
        }
        const lunes = new Date(fechaElegida);
        lunes.setUTCDate(lunes.getUTCDate() - (lunes.getUTCDay() + 6) % 7);
        const domingo = new Date(lunes);
        domingo.setUTCDate(domingo.getUTCDate() + 6);
        semana.textContent = 'Semana seleccionada del ' + formatoFecha.format(lunes)
            + ' al ' + formatoFecha.format(domingo);
    }

    function actualizarCampos() {
        const agrupado = periodo.value === 'mes' || periodo.value === 'anio';
        const usaFechaCompleta = periodo.value === 'dia' || periodo.value === 'semana';
        mostrarCampo(fecha, usaFechaCompleta);
        mostrarCampo(anio, agrupado);
        mostrarCampo(mes, agrupado);
        mostrarCampo(dia, agrupado && mes.value !== '');
        fecha.required = usaFechaCompleta;
        anio.required = agrupado;
        mes.required = periodo.value === 'mes';
        detalle.disabled = !agrupado;
        document.getElementById('filtro-fecha-etiqueta').textContent =
            periodo.value === 'semana' ? 'Elegí un día de la semana' : 'Fecha';
        actualizarSemana();
    }

    function cargarMeses() {
        mes.replaceChildren();
        if (periodo.value === 'anio') mes.add(new Option('Año completo', ''));
        meses.forEach((nombre, indice) => mes.add(new Option(nombre, dosDigitos(indice + 1))));
    }

    function sincronizarFecha() {
        if (anio.disabled || !anio.validity.valid) return;
        fecha.value = anio.value + '-' + (mes.value || '01') + '-' + (dia.disabled ? '01' : dia.value || '01');
        detalle.value = !dia.disabled && dia.value ? 'dia' : periodo.value === 'anio' && mes.value ? 'mes' : '';
    }

    cargarMeses();
    cargarFecha(fecha.value, formulario.dataset.detalle);
    actualizarCampos();

    const camposDePeriodo = [periodo, fecha, anio, mes, dia];
    const seleccionAplicada = leerSeleccion();

    function leerSeleccion() {
        return camposDePeriodo.map(campo => campo.disabled ? '' : campo.value).join('/');
    }

    function actualizarAvisoPendiente() {
        avisoPendiente.hidden = leerSeleccion() === seleccionAplicada;
    }

    periodo.addEventListener('change', function () {
        cargarMeses();
        cargarFecha(fecha.value, '');
        actualizarCampos();
    });

    fecha.addEventListener('input', actualizarSemana);
    [anio, mes].forEach(campo => campo.addEventListener('change', function () {
        cargarDias();
        actualizarCampos();
        sincronizarFecha();
    }));
    dia.addEventListener('change', sincronizarFecha);
    formulario.addEventListener('input', actualizarAvisoPendiente);
    formulario.addEventListener('change', actualizarAvisoPendiente);
    formulario.addEventListener('submit', function () {
        sincronizarFecha();
        fecha.disabled = periodo.value === 'general';
    });

    const estadisticas = document.getElementById('admin-estadisticas');
    if (!estadisticas) return;

    estadisticas.addEventListener('click', function (evento) {
        const enlace = evento.target.closest('a[href^="#"]');
        if (!enlace || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) return;
        const destino = document.getElementById(enlace.hash.slice(1));
        if (!destino || !estadisticas.contains(destino)) return;
        evento.preventDefault();
        destino.focus({preventScroll: true});
        destino.scrollIntoView({block: 'start'});
    });

    if (window.location.hash && estadisticas.contains(document.getElementById(window.location.hash.slice(1)))) {
        window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search);
    }
})();
