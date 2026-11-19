<?php

namespace App\Filament\Resources;

use App\Filament\Pages\Operations;
use App\Models\Review;
use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions as Permissions;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationLabel = 'Đánh giá';

    protected static ?string $modelLabel = 'Đánh giá';

    public static function canViewAny(): bool
    {
        $actor = User::find(auth()->id());

        return $actor && (app(AdminAuthorization::class)->allows($actor, Permissions::MODERATE_REVIEWS) || app(AdminAuthorization::class)->allows($actor, Permissions::MODERATE_REVIEWS));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        abort_unless(static::canViewAny(), 403);
        $actor = User::findOrFail(auth()->id());
        $assign = app(AdminAuthorization::class)->allows($actor, Permissions::MODERATE_REVIEWS);
        $query = parent::getEloquentQuery();
        $query->select(['id', 'program_id', 'rating', 'visibility', 'version']);

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->sortable(), TextColumn::make('program_id')->sortable(), TextColumn::make('rating')->sortable(), TextColumn::make('visibility')->sortable(), TextColumn::make('version')->sortable()])->defaultSort('id', 'desc')->recordActions([Action::make('open')->label('Mở')->url(fn ($record) => Operations::getUrl(['section' => 'review', 'recordId' => $record->id]))])->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ReviewResource\Pages\ListRecords::route('/')];
    }
}
