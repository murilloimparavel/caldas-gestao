<?php

namespace App\Support;

use App\Contracts\TlsCertificateVerifier;

final class NativeTlsCertificateVerifier implements TlsCertificateVerifier
{
    public function verify(string $hostname): ?string
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'peer_name' => $hostname,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
        ]]);
        $socket = @stream_socket_client('tls://'.$hostname.':443', $errorCode, $errorMessage, (float) config('domains.ssl_timeout', 8), STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            return $errorMessage !== '' ? $errorMessage : 'Não foi possível conectar ao certificado SSL.';
        }

        fclose($socket);

        return null;
    }
}
