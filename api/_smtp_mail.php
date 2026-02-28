<?php
// Minimal SMTP sender.
// - STARTTLS on 587 (recommended)
// Returns true/false and fills $err on failure.

function smtp_send_mail(
    string $to,
    string $subject,
    string $htmlBody,
    string $fromEmail,
    string $fromName,
    ?string &$err = null
): bool {
    $err = null;

    $host = SMTP_HOST;         // e.g. smtp.forpsi.com
    $port = (int)SMTP_PORT;    // 587
    $user = SMTP_USER;
    $pass = SMTP_PASS;

    $remote = "tcp://{$host}:{$port}";

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'peer_name'         => $host,
            'SNI_enabled'       => true,
            'SNI_server_name'   => $host,
        ]
    ]);

    $fp = stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) { $err = "CONNECT_FAIL {$errno} {$errstr}"; return false; }

    stream_set_timeout($fp, 15);

    $read = function() use ($fp) {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) break;
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $send = function(string $cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };

    $expect = function(string $resp, array $codes) {
        $code = (int)substr($resp, 0, 3);
        return in_array($code, $codes, true);
    };

    $r = $read(); if (!$expect($r, [220])) { $err = "BAD_GREETING"; fclose($fp); return false; }

    $send("EHLO l2ordo.net");
    $r = $read(); if (!$expect($r, [250])) { $err = "EHLO_FAIL"; fclose($fp); return false; }

    // STARTTLS for 587
    if ($port === 587) {
        $send("STARTTLS");
        $r = $read(); if (!$expect($r, [220])) { $err = "STARTTLS_FAIL"; fclose($fp); return false; }

        $cryptoOk = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($cryptoOk !== true) { $err = "TLS_NEGOTIATION_FAILED"; fclose($fp); return false; }

        $send("EHLO l2ordo.net");
        $r = $read(); if (!$expect($r, [250])) { $err = "EHLO_AFTER_TLS_FAIL"; fclose($fp); return false; }
    }

    $send("AUTH LOGIN");
    $r = $read(); if (!$expect($r, [334])) { $err = "AUTH_LOGIN_FAIL"; fclose($fp); return false; }

    $send(base64_encode($user));
    $r = $read(); if (!$expect($r, [334])) { $err = "AUTH_USER_FAIL"; fclose($fp); return false; }

    $send(base64_encode($pass));
    $r = $read(); if (!$expect($r, [235])) { $err = "AUTH_PASS_FAIL"; fclose($fp); return false; }

    $send("MAIL FROM:<{$fromEmail}>");
    $r = $read(); if (!$expect($r, [250])) { $err = "MAIL_FROM_FAIL"; fclose($fp); return false; }

    $send("RCPT TO:<{$to}>");
    $r = $read(); if (!$expect($r, [250, 251])) { $err = "RCPT_TO_FAIL"; fclose($fp); return false; }

    $send("DATA");
    $r = $read(); if (!$expect($r, [354])) { $err = "DATA_FAIL"; fclose($fp); return false; }

    $headers = [];
    $headers[] = "From: " . mb_encode_mimeheader($fromName, "UTF-8") . " <{$fromEmail}>";
    $headers[] = "To: <{$to}>";
    $headers[] = "Subject: " . mb_encode_mimeheader($subject, "UTF-8");
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";

    $msg = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody;

    // Dot-stuffing
    $msg = preg_replace("/\r\n\./", "\r\n..", $msg);

    fwrite($fp, $msg . "\r\n.\r\n");
    $r = $read(); if (!$expect($r, [250])) { $err = "SEND_FAIL"; fclose($fp); return false; }

    $send("QUIT");
    fclose($fp);
    return true;
}
