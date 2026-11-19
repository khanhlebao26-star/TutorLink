<?php

namespace App\Filament\Pages;

use App\Actions\Admin\ReadAdminWorkspace;
use App\Actions\Admin\RunAdminOperation;
use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Exceptions\HttpResponseException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class Operations extends Page
{
    protected string $view = 'filament.pages.operations';

    protected static ?string $title = 'Điều hành TutorLink';

    #[Url]
    public string $section = 'dashboard';

    #[Url]
    public ?int $recordId = null;

    #[Locked]
    public array $versions = [];

    public string $reason = '';

    public ?int $assignee = null;

    public static function canAccess(): bool
    {
        $actor = User::find(auth()->id());

        return config('admin.panel_enabled') && $actor && app(AdminAuthorization::class)->allows($actor, AdminPermissions::VIEW_DASHBOARD);
    }

    public function mount(): void
    {
        $this->refreshRecord();
    }

    public function refreshRecord(): void
    {
        $data = $this->read();
        $record = $data['profile']['assignment'] ?? $data['report'] ?? $data['review'] ?? $data['assignment'] ?? [];
        $this->versions = ['assignment_version' => (int) ($record['assignment_version'] ?? 0)];
        if (isset($data['review'])) {
            $this->versions['expected_version'] = (int) $data['review']['version'];
        } elseif (isset($data['profile']['revision']['version'])) {
            $this->versions['expected_version'] = (int) $data['profile']['revision']['version'];
        }
    }

    public function operate(string $action, ?int $documentId = null): void
    {
        abort_unless(static::canAccess(), 403);
        $this->read();
        $type = explode('-', $this->section)[0];
        if ($documentId !== null) {
            abort_unless($type === 'profile', 422);
            $data = $this->read();
            abort_unless(collect($data['profile']['verification_documents'] ?? [])->contains('id', $documentId), 404);
            $type = 'document';
        }
        try {
            app(RunAdminOperation::class)->execute(auth()->user(), $type, $documentId ?? (int) $this->recordId, $action, [...$this->versions, 'reason' => $this->reason, 'resolution_note' => $this->reason, 'assigned_admin_id' => $this->assignee]);
            $this->reason = '';
            Notification::make()->success()->title('Đã cập nhật')->send();
            $this->refreshRecord();
        } catch (HttpResponseException $exception) {
            if ($exception->getResponse()->getStatusCode() !== 409) {
                throw $exception;
            }
            Notification::make()->danger()->title('Dữ liệu đã thay đổi. Tải lại trước khi quyết định.')->persistent()->send();
        }
    }

    private function read(): array
    {
        abort_unless(static::canAccess(), 403);

        return app(ReadAdminWorkspace::class)->execute(request(), $this->section, $this->recordId);
    }

    protected function getViewData(): array
    {
        return ['content' => $this->read()];
    }
}
