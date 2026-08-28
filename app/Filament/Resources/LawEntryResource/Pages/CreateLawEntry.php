<?php

namespace App\Filament\Resources\LawEntryResource\Pages;

use App\Filament\Resources\LawEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLawEntry extends CreateRecord
{
    protected static string $resource = LawEntryResource::class;
}
