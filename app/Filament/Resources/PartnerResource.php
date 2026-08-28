<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Partner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'الشركاء';

    protected static ?string $modelLabel = 'شريك';

    protected static ?string $pluralModelLabel = 'الشركاء';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required(),
                Forms\Components\Select::make('partner_type')
                    ->label('نوع الشريك')
                    ->options([
                        'first_party' => 'حكم ورقم (داخلي)',
                        'application_owner' => 'مالك تطبيق',
                        'accounting_firm' => 'مكتب محاسبي',
                        'integration_provider' => 'مزوّد تكامل',
                        'service_provider' => 'مزوّد خدمة',
                        'technology_partner' => 'شريك تقني',
                    ])
                    ->default('first_party')
                    ->required(),
                Forms\Components\TextInput::make('revenue_share_percentage')
                    ->label('نسبة تقاسم الإيراد (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partner_type')
                    ->label('نوع الشريك')
                    ->badge(),
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
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }
}
