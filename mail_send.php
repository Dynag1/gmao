<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclure PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require "conf/conf.php";

function send_mail($to, $subject, $body, $reply_to=null) {
    $mail = new PHPMailer(true);
    require "conf/conf.php";
    try {
        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_user;
        $mail->Password   = $mail_pass;
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = $mail_port;

        $mail->setFrom($mail_mail, $marque);
        $mail->addAddress($to);
        if ($reply_to) $mail->addReplyTo($reply_to);

        $mail->CharSet   = 'UTF-8';      // <---- accents !
        $mail->Encoding  = 'base64';     // <---- facultatif mais bien pour le HTML
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // echo "Erreur exception : " . $e->getMessage();
        return false;
    }
}

?>
