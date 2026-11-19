<?php

return [
    // Keep verification documents mandatory by default; local Docker can opt out for testing.
    'verification_documents_required' => (bool) env('TUTOR_PROFILE_DOCUMENTS_REQUIRED', true),
];
