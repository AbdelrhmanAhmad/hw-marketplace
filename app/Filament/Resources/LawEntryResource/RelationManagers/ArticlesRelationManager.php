<?php

namespace App\Filament\Resources\LawEntryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $title = 'المواد';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('article_number')
                    ->label('رقم المادة')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('content')
                    ->label('نص المادة')
                    ->required()
                    ->columnSpanFull()
                    ->rows(5),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('article_number')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('article_number')
                    ->label('رقم المادة'),
                Tables\Columns\TextColumn::make('content')
                    ->label('نص المادة')
                    ->limit(80),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
