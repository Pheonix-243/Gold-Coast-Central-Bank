<?php
use PHPMailer\PHPMailer\PHPMailer;
require_once 'phpmailer/Exception.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';

function email_send($address, $header, $msg)
{
    $mail = new PHPMailer(true); 
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->Port = 465;
    $mail->SMTPSecure = "ssl";
    $mail->SMTPAuth = true;
    $mail->Username = "myphp0068@gmail.com";
    $mail->Password = "bbenmzofviglbmzb";
    $mail->addAddress($address);
    $mail->setFrom("myphp0068@gmail.com", "Gold Coast Central Bank");
    $mail->Subject  = $header;
    $mail->Body     = $msg;

    try {
        $mail->send();
        echo 'Message has been sent.';
    } catch (Exception $e) {
        echo 'Message was not sent. Mailer Error: ' . $mail->ErrorInfo;
    }
}
?>
