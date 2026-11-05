<?php

namespace App\Filament\Resources;

use App\Filament\Pages\Operations;
use App\Models\Report;
use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions as Permissions;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationLabel = 'Báo cáo';

    protected static ?string $modelLabel = 'Báo cáo';

    public static function canViewAny(): bool
    {
        $actor = User::find(auth()->id());

        return $actor && (app(AdminAuthorization::class)->allows($actor, Permissions::VIEW_REPORTS) || app(AdminAuthorization::class)->allows($actor, Permissions::ASSIGN_REPORTS));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        abort_unless(static::canViewAny(), 403);
        $actor = User::findOrFail(auth()->id());
        $assign = app(AdminAuthorization::class)->allows($actor, Permissions::ASSIGN_REPORTS);
        $query = parent::getEloquentQuery();
        if (! $assign) {
            $query->where('assigned_admin_id', $actor->id);
        }$query->select(['id', 'status', 'assigned_admin_id', 'assignment_version']);

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->sortable(), TextColumn::make('status')->sortable(), TextColumn::make('assigned_admin_id')->sortable(), TextColumn::make('assignment_version')->sortable()])->defaultSort('id', 'desc')->recordActions([Action::make('open')->label('Mở')->url(fn ($record) => Operations::getUrl(['section' => 'report', 'recordId' => $record->id])), Action::make('assign')->label('Phân công')->visible(fn () => auth()->user()->hasPermission('admin.reports.assign'))->url(fn ($record) => Operations::getUrl(['section' => 'report-assignment', 'recordId' => $record->id]))])->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ReportResource\Pages\ListRecords::route('/')];
    }
}
