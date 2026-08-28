<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LawEntryResource\Pages;
use App\Filament\Resources\LawEntryResource\RelationManagers;
use App\Models\LawEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LawEntryResource extends Resource
{
    protected static ?string $model = LawEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'الأنظمة';

    protected static ?string $modelLabel = 'نظام';

    protected static ?string $pluralModelLabel = 'الأنظمة';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('عنوان النظام')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Forms\Set $set) => $set('slug', Str::slug($state)))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف (slug)')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('number')
                    ->label('رقم النظام'),
                Forms\Components\TextInput::make('hijri_date')
                    ->label('التاريخ الهجري'),
                Forms\Components\DatePicker::make('gregorian_date')
                    ->label('التاريخ الميلادي'),
                Forms\Components\Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'نافذ' => 'نافذ',
                        'معلق النفاذ' => 'معلق النفاذ',
                        'ملغى' => 'ملغى',
                    ])
                    ->required()
                    ->default('نافذ'),
                Forms\Components\TextInput::make('issuing_authority')
                    ->label('الجهة المصدرة'),
                Forms\Components\Select::make('categories')
                    ->label('التصنيفات')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                Forms\Components\Textarea::make('summary')
                    ->label('ملخص')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('source_url')
                    ->label('رابط المصدر الرسمي')
                    ->url(),
                Forms\Components\TextInput::make('external_id')
                    ->label('معرف خارجي (للربط المستقبلي بـ API)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable(),
                Tables\Columns\TextColumn::make('number')
                    ->label('الرقم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'نافذ' => 'success',
                        'معلق النفاذ' => 'warning',
                        'ملغى' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('issuing_authority')
                    ->label('الجهة المصدرة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gregorian_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُضيف في')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'نافذ' => 'نافذ',
                        'معلق النفاذ' => 'معلق النفاذ',
                        'ملغى' => 'ملغى',
                    ]),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\ArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLawEntries::route('/'),
            'create' => Pages\CreateLawEntry::route('/create'),
            'edit' => Pages\EditLawEntry::route('/{record}/edit'),
        ];
    }
}
