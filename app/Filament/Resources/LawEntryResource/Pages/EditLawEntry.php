<?php

namespace App\Filament\Resources\LawEntryResource\Pages;

use App\Filament\Resources\LawEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLawEntry extends EditRecord
{
    protected static string $resource = LawEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
