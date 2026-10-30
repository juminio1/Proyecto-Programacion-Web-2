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

            $mail->isHTML(true);

            $mail->Subject = 'Confirmá tu cuenta de Preguntados UNLaM';

            $mail->Body = "
                <!DOCTYPE html>
                <html lang='es'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>

                <body style='
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f7;
                    font-family: Arial, Helvetica, sans-serif;
                    color: #222222;
                '>

                    <div style='
                        max-width: 600px;
                        margin: 40px auto;
                        background-color: #ffffff;
                        border-radius: 12px;
                        padding: 40px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                    '>

                        <h1 style='
                            margin-top: 0;
                            color: #5b32d6;
                            font-size: 28px;
                        '>
                            Preguntados UNLaM
                        </h1>

                        <h2 style='
                            font-size: 22px;
                            color: #222222;
                        '>
                            Bienvenido a Preguntados
                        </h2>

                        <p style='
                            font-size: 16px;
                            line-height: 1.6;
                        '>
                            Recibiste este correo porque se creó una cuenta
                            en Preguntados UNLaM utilizando esta dirección
                            de correo electrónico.
                        </p>

                        <p style='
                            font-size: 16px;
                            line-height: 1.6;
                        '>
                            Para completar el registro y habilitar tu cuenta,
                            necesitás confirmar tu correo electrónico.
                        </p>

                        <div style='
                            text-align: center;
                            margin: 30px 0;
                        '>
                            <a
                                href='$enlaceActivacion'
                                style='
                                    display: inline-block;
                                    background-color: #5b32d6;
                                    color: #ffffff;
                                    text-decoration: none;
                                    padding: 14px 26px;
                                    border-radius: 8px;
                                    font-size: 16px;
                                    font-weight: bold;
                                '
                            >
                                Confirmar mi cuenta
                            </a>
                        </div>

                        <p style='
                            font-size: 13px;
                            line-height: 1.6;
                            color: #777777;
                        '>
                            Si no creaste una cuenta en Preguntados,
                            podés ignorar este mensaje.
                        </p>

                        <hr style='
                            border: 0;
                            border-top: 1px solid #eeeeee;
                            margin: 30px 0;
                        '>

                        <p style='
                            font-size: 12px;
                            line-height: 1.6;
                            color: #999999;
                            margin-bottom: 0;
                        '>
                            Preguntados UNLaM<br>
                            Proyecto académico de Programación Web II · UNLaM
                        </p>

                    </div>

                </body>
                </html>
            ";

            $mail->AltBody =
                "Bienvenido a Preguntados UNLaM\n\n" .
                "Recibiste este correo porque se creó una cuenta utilizando esta dirección.\n\n" .
                "Para completar el registro y activar tu cuenta, ingresá al siguiente enlace:\n\n" .
                $enlaceActivacion . "\n\n" .
                "Si no creaste esta cuenta, podés ignorar este mensaje.\n\n" .
                "Preguntados UNLaM - Proyecto académico de Programación Web II.";

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