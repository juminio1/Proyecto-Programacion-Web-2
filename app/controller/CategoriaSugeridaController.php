<?php

namespace app\controller;

use app\model\enums\RolUsuario;
use DomainException;
use PDOException;
use Redirect;
use Request;

class CategoriaSugeridaController
{
    private $model;
    private $render;

    public function __construct($model, $render)
    {
        $this->model = $model;
        $this->render = $render;
    }

    // Entrada predeterminada que utiliza el Router del proyecto.
    public function show(): void
    {
        $this->mostrarFormularioSugerenciaCategoria();
    }

    public function mostrarFormularioSugerenciaCategoria(): void
    {
        if ($this->tieneSesionDeJugador()) {
            $mensaje = $_SESSION['categoria_sugerida_exito'] ?? '';
            unset($_SESSION['categoria_sugerida_exito']);
            $this->renderizarFormularioSugerenciaCategoria(['mensajeExito' => $mensaje]);
        } else {
            Redirect::to('/login/show');
        }
    }

    public function enviarSugerenciaCategoria(): void
    {
        if ($this->tieneSesionDeJugador()) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $resultado = $this->registrarSugerenciaCategoriaDelJugador();

                if ($resultado['exito']) {
                    $_SESSION['categoria_sugerida_exito'] = $resultado['mensaje'];
                    // Después del POST volvemos por GET: recargar no vuelve a enviarlo.
                    header('Location: /categoriaSugerida/mostrarFormularioSugerenciaCategoria', true, 303);
                } else {
                    $this->renderizarFormularioSugerenciaCategoria([
                        'mensajeError' => $resultado['mensaje'],
                        'nombre' => $resultado['nombre']
                    ]);
                }
            } else {
                Redirect::to('/categoriaSugerida/mostrarFormularioSugerenciaCategoria');
            }
        } else {
            Redirect::to('/login/show');
        }
    }

    private function registrarSugerenciaCategoriaDelJugador(): array
    {
        $nombre = Request::post('nombre', '');
        $nombre = is_string($nombre) ? $nombre : '';
        $resultado = [
            'exito' => false,
            'mensaje' => 'Volvé a abrir el formulario para enviar tu sugerencia.',
            'nombre' => $nombre
        ];

        if ($this->esTokenDelFormularioCategoriaValido(Request::post('csrf_token'))) {
            try {
                // El autor se obtiene de la sesión, nunca de un campo del formulario.
                $id = $this->model->guardarSugerenciaCategoria((int) $_SESSION['usuario_id'], $nombre);
                $resultado['exito'] = $id > 0;
                $resultado['mensaje'] = 'Tu categoría fue enviada correctamente y quedó pendiente de revisión por un editor.';
            } catch (DomainException $error) {
                $resultado['mensaje'] = $error->getMessage();
            } catch (PDOException $error) {
                error_log('Error al sugerir categoría: ' . $error->getMessage());
                $resultado['mensaje'] = 'No se pudo guardar la sugerencia. Intentá nuevamente.';
            }
        }

        return $resultado;
    }

    private function tieneSesionDeJugador(): bool
    {
        return (int) ($_SESSION['usuario_id'] ?? 0) > 0
            && ($_SESSION['rol'] ?? '') === RolUsuario::JUGADOR;
    }

    private function esTokenDelFormularioCategoriaValido($token): bool
    {
        $tokenGuardado = $_SESSION['categoria_sugerida_csrf'] ?? '';

        return is_string($token) && strlen($tokenGuardado) > 0
            && hash_equals($tokenGuardado, $token);
    }

    private function renderizarFormularioSugerenciaCategoria(array $datos): void
    {
        header('Cache-Control: no-store');
        $_SESSION['categoria_sugerida_csrf'] ??= bin2hex(random_bytes(32));
        $datos['tokenCSRF'] = $_SESSION['categoria_sugerida_csrf'];
        $this->render->renderiza('categoriaSugerida', $datos);
    }
}
