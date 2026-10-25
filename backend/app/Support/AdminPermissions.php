<?php

namespace App\Support;

final class AdminPermissions
{
    public const VIEW_TUTOR_PROFILES = 'admin.tutor_profiles.view';

    public const DECIDE_TUTOR_PROFILES = 'admin.tutor_profiles.decide';

    public const DOWNLOAD_VERIFICATION_DOCUMENTS = 'admin.verification_documents.download';

    public const SUSPEND_TUTOR_PROFILES = 'admin.tutor_profiles.suspend';

    public const SUSPEND_ACCOUNTS = 'admin.accounts.suspend';

    public const VIEW_NOTIFICATION_OUTBOX = 'admin.notification_outbox.view';

    public const REPLAY_NOTIFICATION_OUTBOX = 'admin.notification_outbox.replay';

    public const PURGE_NOTIFICATION_DATA = 'admin.notification_outbox.purge';

    public const PURGE_AUDIT_LOGS = 'admin.audit_logs.purge';
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
            self::VIEW_NOTIFICATION_OUTBOX,
            self::REPLAY_NOTIFICATION_OUTBOX,
            self::PURGE_NOTIFICATION_DATA,
            self::PURGE_AUDIT_LOGS,
        ];
    }
}
