<?php

namespace App\Filament\Resources\MarketplaceItemResource\Pages;

use App\Filament\Resources\MarketplaceItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditMarketplaceItem extends EditRecord
{
    protected static string $resource = MarketplaceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['entry_route'] = $this->record->applicationDetail?->entry_route;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $entryRoute = Arr::pull($data, 'entry_route');

        $record->update($data);

        if ($data['type'] === 'application') {
            $record->applicationDetail()->updateOrCreate([], ['entry_route' => $entryRoute ?: null]);
        }

        return $record;
    }
}
