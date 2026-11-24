<?php

declare(strict_types=1);

function send_mail(
    string $to,
    string $subject,
    string $body,
    ?string $htmlBody = null
): array
{
    if (MAIL_MODE !== 'smtp') {
        return [true, ''];
    }

    if (!preg_match('/^[^\s<>"\r\n]+@[^\s<>"\r\n]+$/', $to)) {
        return [false, 'Invalid recipient address.'];
    }

    if (SMTP_USER === '' || SMTP_PASS === '') {
        return [
            false,
            'SMTP credentials are not configured. Set SMTP_USER and SMTP_PASS in config.php.'
        ];
    }

    $remote = (SMTP_SECURE === 'ssl' ? 'ssl://' : 'tcp://')
        . SMTP_HOST . ':' . SMTP_PORT;

    $ssl = [
        'verify_peer' => true,
        'verify_peer_name' => true
    ];

    if (SMTP_CA_FILE !== '') {
        $ssl['cafile'] = SMTP_CA_FILE;
    }

    $context = stream_context_create([
        'ssl' => $ssl
    ]);

    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$fp) {
        return [
            false,
            'Cannot connect to ' . SMTP_HOST . ':' . SMTP_PORT . ' (' . $errstr . ')'
        ];
    }

    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $out = '';
        $deadline = microtime(true) + 15;

        while (true) {
            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                throw new RuntimeException(
                    'SMTP server response timed out.'
                );
            }

            $seconds = (int) floor($remaining);
            $microseconds = (int) (($remaining - $seconds) * 1000000);

            stream_set_timeout($fp, $seconds, $microseconds);

            $line = fgets($fp, 515);

            if ($line === false) {
                $meta = stream_get_meta_data($fp);

                if ($meta['timed_out']) {
                    throw new RuntimeException(
                        'SMTP server response timed out.'
                    );
                }

                throw new RuntimeException(
                    'SMTP connection closed unexpectedly.'
                );
            }

            $out .= $line;

            // SMTP responses end with a three-digit code followed by a space.
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }

        return $out;
    };

    $cmd = function (string $command, array $expectedCodes) use ($fp, $read): string {
        fwrite($fp, $command . "\r\n");

        $response = $read();

        if (!in_array((int) substr($response, 0, 3), $expectedCodes, true)) {
            throw new RuntimeException(
                trim($response) ?: 'No reply from mail server.'
            );
        }

        return $response;
    };

    try {
        // Connect to SMTP server.
        $greeting = $read();

        if (substr($greeting, 0, 3) !== '220') {
            throw new RuntimeException(
                trim($greeting) ?: 'No greeting from mail server.'
            );
        }

        // Identify the client.
        $cmd('EHLO localhost', [250]);

        // Enable TLS encryption.
        if (SMTP_SECURE === 'tls') {
            $cmd('STARTTLS', [220]);

            if (!@stream_socket_enable_crypto(
                $fp,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            )) {
                $last = error_get_last()['message'] ?? '';

                throw new RuntimeException(
                    'Could not start a secure TLS connection.'
                    . (stripos($last, 'verify') !== false
                        ? ' The mail server certificate could not be verified. Check SMTP_CA_FILE.'
                        : '')
                );
            }

            // Identify the client again after TLS.
            $cmd('EHLO localhost', [250]);
        }

        // Gmail SMTP authentication.
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(SMTP_USER), [334]);
        $cmd(base64_encode(SMTP_PASS), [235]);

        // Set sender and recipient.
        $cmd('MAIL FROM:<' . MAIL_FROM . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);

        // Begin email content.
        $cmd('DATA', [354]);

        // Encode email headers.
        $encodeHeader = function (string $value): string {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        };

        $boundary = 'alt_' . bin2hex(random_bytes(16));

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@councilfunds.local>',
            'From: ' . $encodeHeader(MAIL_FROM_NAME) . ' <' . MAIL_FROM . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodeHeader($subject),
            'MIME-Version: 1.0'
        ];

        // Build plain-text and HTML email content.
        if ($htmlBody !== null) {

            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $mimeBody = '';

            // Plain-text version.
            $mimeBody .= '--' . $boundary . "\r\n";
            $mimeBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $mimeBody .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $mimeBody .= quoted_printable_encode($body) . "\r\n";

            // HTML version.
            $mimeBody .= '--' . $boundary . "\r\n";
            $mimeBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $mimeBody .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $mimeBody .= quoted_printable_encode($htmlBody) . "\r\n";

            // End MIME boundary.
            $mimeBody .= '--' . $boundary . "--\r\n";

        } else {

            // Plain-text only email.
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';

            $mimeBody = quoted_printable_encode($body) . "\r\n";
        }

        // Assemble complete email.
        $message = implode("\r\n", $headers)
            . "\r\n\r\n"
            . $mimeBody;

        // Prevent SMTP commands from being interpreted inside email content.
        $message = preg_replace('/(?m)^\./', '..', $message);

        // Normalize line endings and send the email.
        $message = preg_replace("/(?<!\r)\n/", "\r\n", $message);

        fwrite($fp, $message . "\r\n.\r\n");

        // Confirm that Gmail accepted the email.
        $response = $read();

        if (substr($response, 0, 3) !== '250') {
            throw new RuntimeException(
                trim($response) ?: 'Mail server rejected the message.'
            );
        }

        // Close SMTP connection.
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);

        return [true, ''];

    } catch (Throwable $ex) {

        @fclose($fp);

        $message = $ex->getMessage();

        if (preg_match('/^535(?:[- ]5\.7\.8)?\b/m', $message)) {
            return [
                false,
                'Gmail rejected SMTP authentication (535 5.7.8). Use a current Google App Password for SMTP_PASS, make sure SMTP_USER is the same Gmail account, and check that 2-Step Verification is enabled.'
            ];
        }

        return [false, $message];
    }
}
