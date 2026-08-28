<?php

namespace App\Filament\Resources\ServiceInterestResource\Pages;

use App\Filament\Resources\ServiceInterestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceInterests extends ListRecords
{
    protected static string $resource = ServiceInterestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
