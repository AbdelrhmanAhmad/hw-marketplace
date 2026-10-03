<?php

namespace App\Filament\Resources\TechServiceResource\Pages;

use App\Filament\Resources\TechServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechServices extends ListRecords
{
    protected static string $resource = TechServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
