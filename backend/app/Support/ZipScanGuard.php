<?php

namespace App\Support;

use RuntimeException;
use Throwable;
use ZipArchive;

class ZipScanGuard
{
    public function allows(string $bytes, int $deadline): bool
    {
        if (! $this->isZip($bytes)) {
            return true;
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP support is unavailable.');
        }

        $path = tempnam(sys_get_temp_dir(), 'validate-zip-');

        if ($path === false) {
            throw new RuntimeException('Cannot create temporary archive file.');
        }

        $zip = new ZipArchive;
        $opened = false;

        try {
            if (file_put_contents($path, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Cannot write temporary archive file.');
            }

            $openResult = $zip->open(
                $path,
                ZipArchive::RDONLY | ZipArchive::CHECKCONS,
            );

            if ($openResult !== true) {
                if (in_array($openResult, [
                    ZipArchive::ER_NOZIP,
                    ZipArchive::ER_INCONS,
                    ZipArchive::ER_CRC,
                    ZipArchive::ER_EOF,
                    ZipArchive::ER_COMPNOTSUPP,
                    ZipArchive::ER_ENCRNOTSUPP,
                ], true)) {
                    return false;
                }

                throw new RuntimeException('Cannot open archive for validation.');
            }

            $opened = true;

            $maxFile = (int) config(
                'file-validation.archive.max_file_bytes',
                26214400,
            );

            $maxTotal = (int) config(
                'file-validation.archive.max_total_bytes',
                52428800,
            );

            $maxFiles = (int) config(
                'file-validation.archive.max_files',
                1000,
            );

            if (min($maxFile, $maxTotal, $maxFiles) <= 0) {
                throw new RuntimeException('Archive validation limits are invalid.');
            }

            if ($zip->numFiles > $maxFiles) {
                return false;
            }

            $total = strlen($bytes);

            for ($index = 0; $index < $zip->numFiles; $index++) {
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException('Archive validation deadline exceeded.');
                }

                $stat = $zip->statIndex(
                    $index,
                    ZipArchive::FL_UNCHANGED,
                );

                if (
                    $stat === false
                    || ! isset(
                        $stat['size'],
                        $stat['comp_size'],
                        $stat['crc'],
                        $stat['comp_method'],
                        $stat['encryption_method'],
                    )
                    || $stat['size'] < 0
                    || $stat['size'] > $maxFile
                    || $total + $stat['size'] > $maxTotal
                    || $stat['encryption_method'] !== ZipArchive::EM_NONE
                    || ! in_array(
                        $stat['comp_method'],
                        [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE],
                        true,
                    )
                ) {
                    return false;
                }

                if (
                    str_ends_with($stat['name'], '/')
                    && $stat['size'] === 0
                    && $stat['comp_size'] === 0
                ) {
                    continue;
                }

                $stream = $zip->getStreamIndex(
                    $index,
                    ZipArchive::FL_UNCHANGED,
                );

                if (! is_resource($stream)) {
                    throw new RuntimeException('Cannot open archive entry stream.');
                }

                $size = 0;
                $prefix = '';
                $crc = hash_init('crc32b');

                try {
                    while (! feof($stream)) {
                        if (hrtime(true) >= $deadline) {
                            throw new RuntimeException('Archive validation deadline exceeded.');
                        }

                        $chunk = @fread($stream, 65536);

                        if (
                            $chunk === false
                            || ($chunk === '' && ! feof($stream))
                        ) {
                            throw new RuntimeException('Cannot read archive entry stream.');
                        }

                        $size += strlen($chunk);
                        $total += strlen($chunk);
                        $prefix = substr($prefix.$chunk, 0, 16);

                        if (
                            $size > $maxFile
                            || $total > $maxTotal
                            || $size > $stat['size']
                            || $this->isNestedContainer($prefix)
                        ) {
                            return false;
                        }

                        hash_update($crc, $chunk);
                    }
                } finally {
                    fclose($stream);
                }

                if (
                    $size !== $stat['size']
                    || hash_final($crc) !== sprintf('%08x', $stat['crc'])
                ) {
                    return false;
                }
            }

            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Archive validation deadline exceeded.');
            }

            return $total <= $maxTotal;
        } catch (Throwable $exception) {
            throw new RuntimeException('Archive validation is temporarily unavailable.', 0, $exception);
        } finally {
            if ($opened) {
                $zip->close();
            }

            unlink($path);
        }
    }

    private function isZip(string $bytes): bool
    {
        return str_starts_with($bytes, "PK\x03\x04")
            || str_starts_with($bytes, "PK\x05\x06")
            || str_starts_with($bytes, "PK\x07\x08");
    }

    private function isNestedContainer(string $bytes): bool
    {
        // ponytail: embedded containers are rejected by the current archive policy;
        // add bounded recursive inspection when required.
        $signatures = [
            "PK\x03\x04",
            "PK\x05\x06",
            "PK\x07\x08",
            "\x1f\x8b",
            'BZh',
            "7z\xbc\xaf\x27\x1c",
            'Rar!',
            "\xfd7zXZ\x00",
            'MSCF',
            '%PDF-',
            "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1",
        ];

        foreach ($signatures as $magic) {
            if (str_starts_with($bytes, $magic)) {
                return true;
            }
        }

        return false;
    }
}
