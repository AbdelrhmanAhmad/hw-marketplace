<?php

namespace App\Filament\Resources\MarketplaceItemResource\Pages;

use App\Filament\Resources\MarketplaceItemResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateMarketplaceItem extends CreateRecord
{
    protected static string $resource = MarketplaceItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $entryRoute = Arr::pull($data, 'entry_route');

        /** @var Model $record */
        $record = static::getModel()::create($data);

        if ($data['type'] === 'application') {
            $record->applicationDetail()->create(['entry_route' => $entryRoute ?: null]);
        }

        return $record;
    }
}
