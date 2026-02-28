<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendMail(string $to, string $subject, string $html, string $text = ''): void {
  $cfg = require __DIR__ . '/../config/mail.php';

  $mail = new PHPMailer(true);
  $mail->CharSet = 'UTF-8';

  $mail->isSMTP();
  $mail->Host = $cfg['host'];
  $mail->SMTPAuth = true;
  $mail->Username = $cfg['user'];
  $mail->Password = $cfg['pass'];
  $mail->SMTPSecure = $cfg['secure'];
  $mail->Port = (int)$cfg['port'];

  $mail->setFrom($cfg['from_email'], $cfg['from_name']);
  $mail->addAddress($to);

  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body = $html;
  $mail->AltBody = $text ?: strip_tags($html);

  $mail->send();
}
