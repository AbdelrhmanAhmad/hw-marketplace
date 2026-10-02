<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TechServiceResource\Pages;
use App\Models\TechService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * بوابة التقنية — كتالوج الخدمات (تصميم مواقع، تطبيقات...). يديره فريق
 * المنصة حصرًا (is_platform_staff هو الحارس الوحيد، بلا Policy إضافية) —
 * أول كتالوج ثابت يديره الفريق نفسه بمزوّد واحد (المنصة)، بعكس مجتمع
 * الخدمات متعدد المزوّدين.
 */
class TechServiceResource extends Resource
{
    protected static ?string $model = TechService::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'بوابة التقنية';

    protected static ?string $modelLabel = 'خدمة تقنية';

    protected static ?string $pluralModelLabel = 'خدمات بوابة التقنية';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('عنوان الخدمة')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Forms\Set $set) => $set('slug', Str::slug($state)))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف (slug)')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('category')
                    ->label('التصنيف')
                    ->placeholder('مثال: تطوير مواقع'),
                Forms\Components\Textarea::make('description')
                    ->label('الوصف')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price_note')
                    ->label('ملاحظة السعر')
                    ->placeholder('مثال: يبدأ من 3000 ريال — لا بوابة دفع بعد'),
                Forms\Components\TextInput::make('icon')
                    ->label('أيقونة (اختياري)'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('ترتيب العرض')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_published')
                    ->label('منشور للعامة')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('التصنيف')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('منشور')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُضيف في')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListTechServices::route('/'),
            'create' => Pages\CreateTechService::route('/create'),
            'edit' => Pages\EditTechService::route('/{record}/edit'),
        ];
    }
}
