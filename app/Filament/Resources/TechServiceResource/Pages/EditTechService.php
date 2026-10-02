<?php

namespace App\Filament\Resources\TechServiceResource\Pages;

use App\Filament\Resources\TechServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechService extends EditRecord
{
    protected static string $resource = TechServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
