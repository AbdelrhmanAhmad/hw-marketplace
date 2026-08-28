<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSubscriptionResource\Pages;
use App\Models\AppSubscription;
use App\Support\PlatformApps;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppSubscriptionResource extends Resource
{
    protected static ?string $model = AppSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'اشتراكات التطبيقات';

    protected static ?string $modelLabel = 'اشتراك';

    protected static ?string $pluralModelLabel = 'اشتراكات التطبيقات';

    protected static ?string $navigationGroup = 'Core Platform';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('app_key')
                    ->label('التطبيق')
                    ->options(fn () => collect(PlatformApps::all())->pluck('name', 'key'))
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعّال',
                        'cancelled' => 'ملغى',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('app_key')
                    ->label('التطبيق')
                    ->formatStateUsing(fn (string $state) => collect(PlatformApps::all())->firstWhere('key', $state)['name'] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('تاريخ الاشتراك')
                    ->dateTime('d F Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('app_key')
                    ->label('التطبيق')
                    ->options(fn () => collect(PlatformApps::all())->pluck('name', 'key')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListAppSubscriptions::route('/'),
            'create' => Pages\CreateAppSubscription::route('/create'),
            'edit' => Pages\EditAppSubscription::route('/{record}/edit'),
        ];
    }
}
