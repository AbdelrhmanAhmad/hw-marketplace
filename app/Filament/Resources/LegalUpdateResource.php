<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LegalUpdateResource\Pages;
use App\Filament\Resources\LegalUpdateResource\RelationManagers;
use App\Models\LegalUpdate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
class LegalUpdateResource extends Resource
{
    protected static ?string $model = LegalUpdate::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'التحديثات التشريعية';

    protected static ?string $modelLabel = 'تحديث';

    protected static ?string $pluralModelLabel = 'التحديثات التشريعية';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('body')
                    ->label('التفاصيل')
                    ->required()
                    ->columnSpanFull()
                    ->rows(4),
                Forms\Components\DatePicker::make('published_at')
                    ->label('تاريخ النشر')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('law_entry_id')
                    ->label('النظام المرتبط')
                    ->relationship('lawEntry', 'title')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('تاريخ النشر')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lawEntry.title')
                    ->label('النظام المرتبط')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLegalUpdates::route('/'),
            'create' => Pages\CreateLegalUpdate::route('/create'),
            'edit' => Pages\EditLegalUpdate::route('/{record}/edit'),
        ];
    }
}
