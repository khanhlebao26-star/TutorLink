<?php

use App\Support\TaskSubmissionRequestHash;

it('matches the RFC 8785 JCS hash vector for special Unicode submission content', function () {
    $content = "quote: \" slash: / backslash: \\ controls:\u{0001}\t\n separators:\u{2028}\u{2029} emoji: 😀 accents: é/e\u{0301}";

    expect(TaskSubmissionRequestHash::make(1, $content, [10, 2]))
        ->toBe('7ee712c757962f01e53c7efcfddbd6c59612cfc27db2cfa306cfd598b025945e');
});
