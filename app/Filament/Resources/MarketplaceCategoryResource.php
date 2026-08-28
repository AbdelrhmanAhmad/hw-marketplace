<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceCategoryResource\Pages;
use App\Models\MarketplaceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MarketplaceCategoryResource extends Resource
{
    protected static ?string $model = MarketplaceCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'تصنيفات المتجر';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'تصنيفات المتجر';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Forms\Set $set) => $set('slug', Str::slug($state))),
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف (slug)')
                    ->required()
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('المعرف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('marketplace_items_count')
                    ->label('عدد العناصر')
                    ->counts('marketplaceItems'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListMarketplaceCategories::route('/'),
            'create' => Pages\CreateMarketplaceCategory::route('/create'),
            'edit' => Pages\EditMarketplaceCategory::route('/{record}/edit'),
        ];
    }
}
