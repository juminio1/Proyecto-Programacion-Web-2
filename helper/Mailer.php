<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

class Mailer
{
    public function enviarPrueba(
        string $destinatario,
        string $smtpUsuario,
        string $smtpPassword
    ): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            $mail->Username = $smtpUsuario;
            $mail->Password = $smtpPassword;

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom($smtpUsuario, 'Preguntados UNLaM');
            $mail->addAddress($destinatario);

            $mail->isHTML(true);

            $mail->Subject = 'Prueba de correo - Preguntados';
            $mail->Body = '
                <h2>Correo de prueba</h2>
                <p>El envío de correos funciona correctamente.</p>
            ';

            $mail->send();

            return true;

        } catch (Exception $e) {
            Log::error(
                'Error al enviar correo de activación: ' . $mail->ErrorInfo
            );

            return false;
        }
    }

    public function enviarActivacion(
        string $destinatario,
        string $smtpUsuario,
        string $smtpPassword,
        string $enlaceActivacion
    ): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            $mail->Username = $smtpUsuario;
            $mail->Password = $smtpPassword;

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom($smtpUsuario, 'Preguntados UNLaM');
            $mail->addAddress($destinatario);

            $mail->isHTML(true);

            $mail->Subject = 'Activa tu cuenta de Preguntados';

            $mail->Body = "
                <h2>Bienvenido a Preguntados</h2>

                <p>Tu cuenta fue creada correctamente.</p>

                <p>Para activarla, hacé clic en el siguiente enlace:</p>

                <p>
                    <a href='$enlaceActivacion'>
                        Activar mi cuenta
                    </a>
                </p>

                <p>Si no creaste esta cuenta, podés ignorar este correo.</p>
            ";

            $mail->send();

            return true;
        } catch (Exception $e) {
            die('ERROR SMTP: ' . $mail->ErrorInfo);
        }
    }
}