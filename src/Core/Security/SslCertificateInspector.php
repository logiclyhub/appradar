<?php

namespace AppRadar\Agent\Core\Security;

use Throwable;

final class SslCertificateInspector
{
    public function inspect(string $host, int $port = 443, float $timeoutSeconds = 3.0): SslCertificateResult
    {
        $host = trim($host);

        if ($host === '') {
            return new SslCertificateResult(
                host: '',
                reached: false,
                verified: false,
                hostnameMatches: false,
                expired: false,
                daysRemaining: null,
                validFrom: null,
                validTo: null,
                message: 'No host provided',
            );
        }

        try {
            $verifiedClient = $this->connect($host, $port, $timeoutSeconds, verifyPeer: true);

            if ($verifiedClient !== null) {
                $parsed = $this->parsePeerCertificate($verifiedClient, $host);
                fclose($verifiedClient);

                return $parsed;
            }

            $insecureClient = $this->connect($host, $port, $timeoutSeconds, verifyPeer: false);

            if ($insecureClient === null) {
                return new SslCertificateResult(
                    host: $host,
                    reached: false,
                    verified: false,
                    hostnameMatches: false,
                    expired: false,
                    daysRemaining: null,
                    validFrom: null,
                    validTo: null,
                    message: 'Unable to open TLS connection',
                );
            }

            $parsed = $this->parsePeerCertificate($insecureClient, $host, verified: false);
            fclose($insecureClient);

            return $parsed;
        } catch (Throwable $throwable) {
            return new SslCertificateResult(
                host: $host,
                reached: false,
                verified: false,
                hostnameMatches: false,
                expired: false,
                daysRemaining: null,
                validFrom: null,
                validTo: null,
                message: $throwable->getMessage(),
            );
        }
    }

    /**
     * @return resource|null
     */
    private function connect(string $host, int $port, float $timeoutSeconds, bool $verifyPeer)
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'allow_self_signed' => ! $verifyPeer,
            ],
        ]);

        $client = @stream_socket_client(
            'ssl://'.$host.':'.$port,
            $errorCode,
            $errorMessage,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        return is_resource($client) ? $client : null;
    }

    /**
     * @param  resource  $client
     */
    private function parsePeerCertificate($client, string $host, bool $verified = true): SslCertificateResult
    {
        $params = stream_context_get_params($client);
        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($certificate === null) {
            return new SslCertificateResult(
                host: $host,
                reached: true,
                verified: false,
                hostnameMatches: false,
                expired: false,
                daysRemaining: null,
                validFrom: null,
                validTo: null,
                message: 'Peer certificate not available',
            );
        }

        $parsed = openssl_x509_parse($certificate);

        if (! is_array($parsed)) {
            return new SslCertificateResult(
                host: $host,
                reached: true,
                verified: false,
                hostnameMatches: false,
                expired: false,
                daysRemaining: null,
                validFrom: null,
                validTo: null,
                message: 'Unable to parse peer certificate',
            );
        }

        $validFromTs = isset($parsed['validFrom_time_t']) ? (int) $parsed['validFrom_time_t'] : null;
        $validToTs = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null;
        $now = time();
        $expired = $validToTs !== null ? $validToTs < $now : false;
        $daysRemaining = $validToTs !== null ? (int) floor(($validToTs - $now) / 86400) : null;
        $hostnameMatches = $this->hostnameMatches($parsed, $host);

        return new SslCertificateResult(
            host: $host,
            reached: true,
            verified: $verified && $hostnameMatches && ! $expired,
            hostnameMatches: $hostnameMatches,
            expired: $expired,
            daysRemaining: $daysRemaining,
            validFrom: $validFromTs !== null ? gmdate('c', $validFromTs) : null,
            validTo: $validToTs !== null ? gmdate('c', $validToTs) : null,
            message: $verified ? null : 'Certificate could not be verified',
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function hostnameMatches(array $parsed, string $host): bool
    {
        $names = [];

        $cn = $parsed['subject']['CN'] ?? null;
        if (is_string($cn) && $cn !== '') {
            $names[] = $cn;
        }

        $altNames = $parsed['extensions']['subjectAltName'] ?? null;
        if (is_string($altNames)) {
            foreach (explode(',', $altNames) as $altName) {
                $altName = trim($altName);
                if (str_starts_with(strtolower($altName), 'dns:')) {
                    $names[] = substr($altName, 4);
                }
            }
        }

        foreach ($names as $name) {
            if ($this->nameMatchesHost($name, $host)) {
                return true;
            }
        }

        return false;
    }

    private function nameMatchesHost(string $name, string $host): bool
    {
        $name = strtolower(trim($name));
        $host = strtolower(trim($host));

        if ($name === $host) {
            return true;
        }

        if (str_starts_with($name, '*.')) {
            $suffix = substr($name, 1);

            return str_ends_with($host, $suffix) && substr_count($host, '.') === substr_count($name, '.');
        }

        return false;
    }
}
