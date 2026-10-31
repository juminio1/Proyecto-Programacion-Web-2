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

            $mail->CharSet = 'UTF-8';

            $mail->setFrom($smtpUsuario, 'Preguntados UNLaM');
            $mail->addAddress($destinatario);

            $mail->isHTML(true);

            $mail->Subject = 'Prueba de correo - Preguntados';

            $mail->Body = '
                <h2>Correo de prueba</h2>
                <p>El envío de correos funciona correctamente.</p>
            ';

            $mail->AltBody =
                "Correo de prueba\n\n" .
                "El envío de correos funciona correctamente.";

            $mail->send();

            return true;

        } catch (Exception $e) {
            Log::error(
                'Error al enviar correo de prueba: ' . $mail->ErrorInfo
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

            $mail->CharSet = 'UTF-8';

            $mail->setFrom($smtpUsuario, 'Preguntados UNLaM');
            $mail->addAddress($destinatario);

            // Para esta prueba enviamos texto plano, sin HTML.
            $mail->isHTML(false);

            $mail->Subject = 'Activación de cuenta - Preguntados';

            $mail->Body =
                "Bienvenido a Preguntados.\n\n" .
                "Recibiste este correo porque se creó una cuenta utilizando esta dirección de correo electrónico.\n\n" .
                "Para activar tu cuenta, ingresá al siguiente enlace:\n\n" .
                $enlaceActivacion . "\n\n" .
                "Si no creaste esta cuenta, podés ignorar este mensaje.\n\n" .
                "Preguntados - Proyecto académico de Programación Web II.";

            $mail->send();

            return true;

        } catch (Exception $e) {
            Log::error(
                'Error al enviar correo de activación: ' . $mail->ErrorInfo
            );

            return false;
        }
    }
}