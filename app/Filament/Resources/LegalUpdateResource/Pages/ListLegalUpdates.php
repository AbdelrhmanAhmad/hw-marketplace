<?php

namespace App\Filament\Resources\LegalUpdateResource\Pages;

use App\Filament\Resources\LegalUpdateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLegalUpdates extends ListRecords
{
    protected static string $resource = LegalUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
