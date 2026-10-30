<?php

namespace App\Support;

final class TaskSubmissionRequestHash
{
    public const MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    /** @param list<int> $fileIds */
    public static function make(int $expectedSubmissionVersion, ?string $content, array $fileIds): string
    {
        sort($fileIds, SORT_NUMERIC);

        // These fixed ASCII keys are already in RFC 8785 order. Values are strings/null and safe integers.
        $canonicalJson = json_encode([
            'content' => $content,
            'expected_submission_version' => $expectedSubmissionVersion,
            'file_ids' => array_values($fileIds),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);

        return hash('sha256', $canonicalJson);
    }
}
