<?php

namespace App\Filament\Resources\LawEntryResource\Pages;

use App\Filament\Resources\LawEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLawEntries extends ListRecords
{
    protected static string $resource = LawEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
