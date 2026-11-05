<?php

namespace App\Filament\Resources;

use App\Filament\Pages\Operations;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions as Permissions;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TutorProfileResource extends Resource
{
    protected static ?string $model = TutorProfile::class;

    protected static ?string $navigationLabel = 'Hồ sơ gia sư';

    protected static ?string $modelLabel = 'Hồ sơ gia sư';

    public static function canViewAny(): bool
    {
        $actor = User::find(auth()->id());

        return $actor && (app(AdminAuthorization::class)->allows($actor, Permissions::VIEW_TUTOR_PROFILES) || app(AdminAuthorization::class)->allows($actor, Permissions::ASSIGN_TUTOR_PROFILES));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        abort_unless(static::canViewAny(), 403);
        $actor = User::findOrFail(auth()->id());
        $assign = app(AdminAuthorization::class)->allows($actor, Permissions::ASSIGN_TUTOR_PROFILES);
        $query = parent::getEloquentQuery();
        $query->whereHas('user')->whereIn('status', ['submitted', 'changes_requested', 'approved', 'rejected', 'suspended']);
        if (! $assign) {
            $query->where('assigned_admin_id', $actor->id);
        }$query->select(['id', 'status', 'assigned_admin_id', 'assignment_version']);

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->sortable(), TextColumn::make('status')->sortable(), TextColumn::make('assigned_admin_id')->sortable(), TextColumn::make('assignment_version')->sortable()])->defaultSort('id', 'desc')->recordActions([Action::make('open')->label('Mở')->url(fn ($record) => Operations::getUrl(['section' => 'profile', 'recordId' => $record->id])), Action::make('assign')->label('Phân công')->visible(fn () => auth()->user()->hasPermission('admin.tutor_profiles.assign'))->url(fn ($record) => Operations::getUrl(['section' => 'profile-assignment', 'recordId' => $record->id]))])->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => TutorProfileResource\Pages\ListRecords::route('/')];
    }
}
