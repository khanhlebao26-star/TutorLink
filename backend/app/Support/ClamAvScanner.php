<?php

namespace App\Support;

use RuntimeException;

class ClamAvScanner
{
    /** @return array{status:string,version:?string,reason:string} */
    public function scan(string $bytes): array
    {
        $timeout = min(
            10,
            max(0.01, (float) config('file-scanning.timeout_seconds', 10)),
        );

        $deadline = hrtime(true) + (int) ($timeout * 1_000_000_000);

        try {
            if (strlen($bytes) > (int) config('file-scanning.max_bytes', 10485760)) {
                return [
                    'status' => 'unavailable',
                    'version' => null,
                    'reason' => 'stream_limit',
                ];
            }

            $version = $this->exchange('VERSION', null, $deadline);

            if (! app(ZipScanGuard::class)->allows($bytes, $deadline)) {
                return [
                    'status' => 'unavailable',
                    'version' => null,
                    'reason' => 'incomplete_scan',
                ];
            }

            if (! preg_match('/\AClamAV [^\r\n\x00]{1,240}\z/', $version)) {
                throw new RuntimeException('Invalid scanner version.');
            }

            $reply = $this->exchange('INSTREAM', $bytes, $deadline);

            if ($reply === 'stream: OK') {
                if ($version !== $this->exchange('VERSION', null, $deadline)) {
                    throw new RuntimeException(
                        'Signature database changed during scan; retry.'
                    );
                }

                return [
                    'status' => 'clean',
                    'version' => $version,
                    'reason' => 'ok',
                ];
            }

            if (preg_match('/\Astream: (.+) FOUND\z/', $reply, $match)) {
                if (
                    preg_match(
                        '/\AHeuristics\.(Limits\.Exceeded|Encrypted|Broken)/',
                        $match[1],
                    )
                ) {
                    return [
                        'status' => 'unavailable',
                        'version' => null,
                        'reason' => 'incomplete_scan',
                    ];
                }

                return [
                    'status' => 'rejected',
                    'version' => $version,
                    'reason' => 'malware_detected',
                ];
            }

            throw new RuntimeException(
                'Scanner did not provide a conclusive verdict.'
            );
        } catch (RuntimeException $exception) {
            return [
                'status' => 'unavailable',
                'version' => null,
                'reason' => 'scanner_unavailable',
            ];
        }
    }

    private function exchange(
        string $command,
        ?string $bytes,
        int $deadline,
    ): string {
        $remaining = $this->remaining($deadline);
        $host = (string) config('file-scanning.host', 'clamav');
        $port = (int) config('file-scanning.port', 3310);

        $socket = @stream_socket_client(
            'tcp://'.$host.':'.$port,
            $errorCode,
            $errorMessage,
            min(2, $remaining),
        );

        if ($socket === false) {
            throw new RuntimeException('Scanner connection failed.');
        }

        try {
            stream_set_blocking($socket, false);

            $this->writeAll($socket, 'z'.$command."\0", $deadline);

            if ($bytes !== null) {
                for (
                    $offset = 0, $size = strlen($bytes);
                    $offset < $size;
                    $offset += 65536
                ) {
                    $chunk = substr($bytes, $offset, 65536);

                    $this->writeAll(
                        $socket,
                        pack('N', strlen($chunk)).$chunk,
                        $deadline,
                    );
                }

                $this->writeAll($socket, pack('N', 0), $deadline);
            }

            $reply = '';

            while (strlen($reply) <= 4096) {
                $this->wait($socket, false, $deadline);
                $part = fread($socket, 4096);

                if ($part === false || ($part === '' && feof($socket))) {
                    throw new RuntimeException('Truncated scanner response.');
                }

                $reply .= $part;
                $end = strpos($reply, "\0");

                if ($end !== false) {
                    if ($end !== strlen($reply) - 1) {
                        throw new RuntimeException(
                            'Unexpected scanner response framing.'
                        );
                    }

                    return substr($reply, 0, $end);
                }
            }

            throw new RuntimeException('Scanner response exceeded limit.');
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function writeAll(
        $socket,
        string $bytes,
        int $deadline,
    ): void {
        $offset = 0;

        while ($offset < strlen($bytes)) {
            $this->wait($socket, true, $deadline);
            $written = @fwrite($socket, substr($bytes, $offset));

            if ($written === false || $written === 0) {
                throw new RuntimeException('Scanner write failed.');
            }

            $offset += $written;
        }
    }

    /** @param resource $socket */
    private function wait(
        $socket,
        bool $writing,
        int $deadline,
    ): void {
        $remaining = $this->remaining($deadline);
        $read = $writing ? [] : [$socket];
        $write = $writing ? [$socket] : [];
        $except = [];

        $seconds = (int) floor($remaining);
        $microseconds = min(
            999999,
            max(1, (int) (($remaining - $seconds) * 1_000_000)),
        );

        if (
            @stream_select(
                $read,
                $write,
                $except,
                $seconds,
                $microseconds,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Scanner timed out or socket failed.'
            );
        }
    }

    private function remaining(int $deadline): float
    {
        $seconds = ($deadline - hrtime(true)) / 1_000_000_000;

        if ($seconds <= 0) {
            throw new RuntimeException(
                'Scanner total deadline exceeded.'
            );
        }

        return $seconds;
    }
}
