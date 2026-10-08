<?php

namespace App\Support;

final class AdminPermissions
{
    public const VIEW_TUTOR_PROFILES = 'admin.tutor_profiles.view';

    public const DECIDE_TUTOR_PROFILES = 'admin.tutor_profiles.decide';

    public const DOWNLOAD_VERIFICATION_DOCUMENTS = 'admin.verification_documents.download';

    public const SUSPEND_TUTOR_PROFILES = 'admin.tutor_profiles.suspend';

    public const SUSPEND_ACCOUNTS = 'admin.accounts.suspend';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_TUTOR_PROFILES,
            self::DECIDE_TUTOR_PROFILES,
            self::DOWNLOAD_VERIFICATION_DOCUMENTS,
            self::SUSPEND_TUTOR_PROFILES,
            self::SUSPEND_ACCOUNTS,
        ];
    }
}
