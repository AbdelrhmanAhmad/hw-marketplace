<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceInterestResource\Pages;
use App\Models\ServiceInterest;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceInterestResource extends Resource
{
    protected static ?string $model = ServiceInterest::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'اهتمامات الخدمات';

    protected static ?string $modelLabel = 'اهتمام';

    protected static ?string $pluralModelLabel = 'اهتمامات الخدمات';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('service_name')
                    ->label('الخدمة')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime('d F Y - h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('service_name')
                    ->label('الخدمة')
                    ->options(fn () => ServiceInterest::query()->distinct()->pluck('service_name', 'service_name')->toArray()),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceInterests::route('/'),
        ];
    }
}
