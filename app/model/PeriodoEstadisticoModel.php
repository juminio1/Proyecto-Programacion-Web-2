<?php

namespace app\model;

use DateTimeImmutable;
use InvalidArgumentException;

final class PeriodoEstadisticoModel
{
    public const OPCIONES = ['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes', 'anio' => 'Año'];

    public string $tipo;
    private string $detalle;
    public DateTimeImmutable $referencia;
    public DateTimeImmutable $desde;
    public DateTimeImmutable $hasta; // Primer día fuera del período.

    public function __construct($tipo, $fecha, $detalle = '')
    {
        if (!is_string($tipo) || !isset(self::OPCIONES[$tipo])) {
            throw new InvalidArgumentException('Elegí día, semana, mes o año.');
        }
        if (!is_string($fecha) || !preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $fecha)) {
            throw new InvalidArgumentException('Elegí una fecha válida.');
        }
        $referencia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$referencia || $referencia->format('Y-m-d') !== $fecha || $fecha < '1001-01-01' || $fecha > '9998-12-31') {
            throw new InvalidArgumentException('Elegí una fecha válida.');
        }

        $detallesPermitidos = [
            'dia' => [''],
            'semana' => [''],
            'mes' => ['', 'dia'],
            'anio' => ['', 'mes', 'dia'],
        ];
        if (!in_array($detalle, $detallesPermitidos[$tipo], true)) {
            throw new InvalidArgumentException('Elegí un detalle válido para el período.');
        }

        $this->tipo = $tipo;
        $this->referencia = $referencia;
        $this->detalle = $detalle;
        switch ($detalle ?: $tipo) {
            case 'semana':
                $this->desde = $referencia->modify('monday this week');
                $this->hasta = $this->desde->modify('+1 week');
                break;
            case 'mes':
                $this->desde = $referencia->modify('first day of this month');
                $this->hasta = $this->desde->modify('+1 month');
                break;
            case 'anio':
                $this->desde = $referencia->setDate((int) $referencia->format('Y'), 1, 1);
                $this->hasta = $this->desde->modify('+1 year');
                break;
            default:
                $this->desde = $referencia;
                $this->hasta = $referencia->modify('+1 day');
        }
    }

    public function parametrosSql(): array
    {
        return ['desde' => $this->desde->format('Y-m-d'), 'hasta' => $this->hasta->format('Y-m-d')];
    }

    public function datosParaVista(): array
    {
        $opciones = [];
        foreach (self::OPCIONES as $valor => $nombre) {
            $opciones[] = ['valor' => $valor, 'nombre' => $nombre, 'activo' => $valor === $this->tipo];
        }
        $fin = $this->hasta->modify('-1 day');
        $rango = $this->desde == $fin
            ? $this->desde->format('d/m/Y')
            : 'del ' . $this->desde->format('d/m/Y') . ' al ' . $fin->format('d/m/Y');
        return [
            'periodo' => $this->tipo,
            'detalle' => $this->detalle,
            'fecha' => $this->referencia->format('Y-m-d'),
            'periodos' => $opciones,
            'rangoAplicado' => self::OPCIONES[$this->detalle ?: $this->tipo] . ' · ' . $rango,
        ];
    }
}
