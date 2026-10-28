<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    protected $table = 'files';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'immutable_datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isComplete(): bool
    {
        // Preserve the legacy lifecycle flag for non-message policies.
        // This flag alone is not proof of an antivirus scan.
        return $this->scan_status === 'clean';
    }

    public function hasScanEvidence(): bool
    {
        return in_array($this->scan_status, ['clean', 'rejected'], true)
            && $this->scan_engine === 'clamav'
            && is_string($this->scan_version)
            && $this->scan_version !== ''
            && $this->scanned_at !== null
            && is_string($this->scanned_sha256)
            && preg_match('/\A[a-f0-9]{64}\z/', $this->scanned_sha256)
            && is_string($this->checksum_sha256)
            && hash_equals(
                $this->checksum_sha256,
                $this->scanned_sha256,
            );
    }

    public function hasVerifiedScan(): bool
    {
        return $this->scan_status === 'clean'
            && $this->hasScanEvidence();
    }
}
