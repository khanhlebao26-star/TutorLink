<?php

namespace App\Support;

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
            return false;
        }

        $path = tempnam(sys_get_temp_dir(), 'scan-zip-');

        if ($path === false) {
            return false;
        }

        $zip = new ZipArchive;
        $opened = false;

        try {
            if (file_put_contents($path, $bytes) !== strlen($bytes)) {
                return false;
            }

            $opened = $zip->open(
                $path,
                ZipArchive::RDONLY | ZipArchive::CHECKCONS,
            ) === true;

            $maxFile = (int) config(
                'file-scanning.archive.max_file_bytes',
                26214400,
            );

            $maxTotal = (int) config(
                'file-scanning.archive.max_total_bytes',
                52428800,
            );

            $maxFiles = (int) config(
                'file-scanning.archive.max_files',
                1000,
            );

            if (
                ! $opened
                || $zip->numFiles > $maxFiles
                || min($maxFile, $maxTotal, $maxFiles) <= 0
            ) {
                return false;
            }

            $total = strlen($bytes);

            for ($index = 0; $index < $zip->numFiles; $index++) {
                if (hrtime(true) >= $deadline) {
                    return false;
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
                    return false;
                }

                $size = 0;
                $prefix = '';
                $crc = hash_init('crc32b');

                try {
                    while (! feof($stream)) {
                        if (hrtime(true) >= $deadline) {
                            return false;
                        }

                        $chunk = @fread($stream, 65536);

                        if (
                            $chunk === false
                            || ($chunk === '' && ! feof($stream))
                        ) {
                            return false;
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

            return $total <= $maxTotal
                && hrtime(true) < $deadline;
        } catch (Throwable $exception) {
            return false;
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
        // ponytail: embedded containers stay pending;
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
