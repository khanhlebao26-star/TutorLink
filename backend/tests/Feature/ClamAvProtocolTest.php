<?php

use App\Support\ClamAvScanner;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('INSTREAM framing and scanner replies use a shared deadline', function (string $mode, string $expected): void {
    $script = <<<'PHP'
        $mode = $argv[1];
        $server = stream_socket_server('tcp://127.0.0.1:0');

        fwrite(
            STDOUT,
            'PORT:'.stream_socket_get_name($server, false)."\n"
        );
        fflush(STDOUT);

        function exact($socket, int $length): string
        {
            $result = '';

            while (strlen($result) < $length) {
                $part = fread($socket, $length - strlen($result));

                if ($part === false || $part === '') {
                    exit(3);
                }

                $result .= $part;
            }

            return $result;
        }

        for ($n = 0; $n < 3; $n++) {
            $client = stream_socket_accept($server, 3);

            if ($client === false) {
                exit(4);
            }

            stream_set_timeout($client, 3);

            $command = '';

            while (($byte = exact($client, 1)) !== "\0") {
                $command .= $byte;
            }

            if ($command === 'zVERSION') {
                $reply = 'ClamAV TEST-PROTOCOL/1/test';

                if ($mode === 'version_change' && $n === 2) {
                    $reply = 'ClamAV TEST-PROTOCOL/2/test';
                }
            } elseif ($command === 'zINSTREAM') {
                $data = '';

                while (
                    ($size = unpack(
                        'Nlength',
                        exact($client, 4)
                    )['length']) !== 0
                ) {
                    $data .= exact($client, $size);
                }

                fwrite(
                    STDOUT,
                    'SHA256:'.hash('sha256', $data)."\n"
                );

                $reply = match ($mode) {
                    'malware' =>
                        'stream: Test.Signature FOUND',

                    'limit' =>
                        'stream: Heuristics.Limits.Exceeded.MaxScanSize FOUND',

                    'encrypted' =>
                        'stream: Heuristics.Encrypted.PDF FOUND',

                    'error' =>
                        'stream: scanner failed ERROR',

                    'malformed' =>
                        'unexpected response',

                    default =>
                        'stream: OK',
                };

                if ($mode === 'timeout') {
                    usleep(600000);
                }
            } else {
                exit(5);
            }

            if (
                $mode === 'truncated'
                && $command === 'zINSTREAM'
            ) {
                fwrite($client, $reply);
            } else {
                @fwrite($client, substr($reply, 0, 3));
                usleep(1000);
                @fwrite($client, substr($reply, 3)."\0");
            }

            fclose($client);

            if (
                $command === 'zINSTREAM'
                && ! in_array(
                    $mode,
                    ['clean', 'version_change'],
                    true
                )
            ) {
                break;
            }
        }

        fclose($server);
        PHP;

    $worker = new Process([
        PHP_BINARY,
        '-r',
        $script,
        $mode,
    ]);

    $worker->setTimeout(5);
    $worker->start();

    try {
        $until = microtime(true) + 3;

        while (
            ! preg_match(
                '/PORT:127\.0\.0\.1:(\d+)/',
                $worker->getOutput(),
                $port,
            )
        ) {
            if (! $worker->isRunning() || microtime(true) > $until) {
                throw new RuntimeException(
                    'Protocol fixture failed: '.$worker->getErrorOutput()
                );
            }

            usleep(10000);
        }

        config([
            'file-scanning.host' => '127.0.0.1',
            'file-scanning.port' => (int) $port[1],
            'file-scanning.timeout_seconds' => $mode === 'timeout'
                ? 0.1
                : 2,
        ]);

        $bytes = str_repeat('chunk-data-', 13000);
        $started = hrtime(true);

        $result = app(ClamAvScanner::class)->scan($bytes);

        expect($result['status'])->toBe($expected);

        if ($mode === 'timeout') {
            expect((hrtime(true) - $started) / 1e9)
                ->toBeLessThan(0.5);
        } else {
            $worker->wait();

            expect($worker->getExitCode())->toBe(0);
        }

        expect($worker->getOutput())
            ->toContain('SHA256:'.hash('sha256', $bytes));
    } finally {
        if ($worker->isRunning()) {
            $worker->stop(0, 9);
        }
    }
})->with([
    ['clean', 'clean'],
    ['malware', 'rejected'],
    ['limit', 'unavailable'],
    ['encrypted', 'unavailable'],
    ['error', 'unavailable'],
    ['malformed', 'unavailable'],
    ['truncated', 'unavailable'],
    ['timeout', 'unavailable'],
    ['version_change', 'unavailable'],
]);
