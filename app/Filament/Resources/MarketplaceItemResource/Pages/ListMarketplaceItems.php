<?php

namespace App\Filament\Resources\MarketplaceItemResource\Pages;

use App\Filament\Resources\MarketplaceItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketplaceItems extends ListRecords
{
    protected static string $resource = MarketplaceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
