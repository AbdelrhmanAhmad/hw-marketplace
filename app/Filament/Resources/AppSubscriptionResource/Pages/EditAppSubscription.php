<?php

namespace App\Filament\Resources\AppSubscriptionResource\Pages;

use App\Filament\Resources\AppSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppSubscription extends EditRecord
{
    protected static string $resource = AppSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
