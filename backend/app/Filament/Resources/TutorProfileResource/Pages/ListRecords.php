<?php

namespace App\Filament\Resources\TutorProfileResource\Pages;

use App\Filament\Resources\TutorProfileResource;

class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = TutorProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
