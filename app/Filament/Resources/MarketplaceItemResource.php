<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceItemResource\Pages;
use App\Models\MarketplaceItem;
use App\Support\PlatformApps;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketplaceItemResource extends Resource
{
    protected static ?string $model = MarketplaceItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationLabel = 'عناصر المتجر';

    protected static ?string $modelLabel = 'عنصر متجر';

    protected static ?string $pluralModelLabel = 'عناصر المتجر';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('الهوية العامة')
                    ->description('حقول مشتركة لكل أنواع عناصر المتجر (Application/Integration/Service) — راجع docs/marketplace-architecture-blueprint.md قسم 1.')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('المعرّف (key)')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->label('النوع')
                            ->options([
                                'application' => 'تطبيق (Application)',
                            ])
                            ->default('application')
                            ->required()
                            ->helperText('Integration وService غير مدعومَين بعد — Phase 1a كتالوج تطبيقات فقط.'),
                        Forms\Components\Select::make('partner_id')
                            ->label('الشريك')
                            ->relationship('partner', 'name')
                            ->required(),
                        Forms\Components\Select::make('category_id')
                            ->label('التصنيف')
                            ->relationship('category', 'name')
                            ->nullable(),
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم')
                            ->required(),
                        Forms\Components\TextInput::make('tagline')
                            ->label('الوصف المختصر (Tagline)')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف الكامل')
                            ->required()
                            ->rows(3),
                        Forms\Components\TextInput::make('icon')
                            ->label('مفتاح الأيقونة')
                            ->helperText('يطابق أسماء الأيقونات المسجَّلة بمكوّن x-service-icon.')
                            ->required(),
                        Forms\Components\Select::make('pricing_model')
                            ->label('نموذج التسعير')
                            ->options([
                                'free' => 'مجاني',
                            ])
                            ->nullable()
                            ->helperText('اتركه فارغًا لعنصر لم يُحدَّد نموذج تسعيره بعد — لا خيار "مدفوع" حتى يوجد عنصر مدفوع حقيقي (لا بيانات وهمية).'),
                        Forms\Components\CheckboxList::make('compatibility')
                            ->label('الجمهور المستهدف')
                            ->options(PlatformApps::audiences())
                            ->columns(2),
                    ]),

                Forms\Components\Section::make('إعدادات التطبيق (Application)')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'application')
                    ->schema([
                        Forms\Components\TextInput::make('entry_route')
                            ->label('اسم الـRoute (نقطة الدخول)')
                            ->helperText('اسم Route فعلي مسجَّل بالتطبيق (مثال: marefa.home). اتركه فارغًا لعنصر "قريبًا" بلا نقطة دخول بعد.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('المعرّف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('الشريك'),
                Tables\Columns\IconColumn::make('applicationDetail.entry_route')
                    ->label('نقطة دخول؟')
                    ->boolean()
                    ->getStateUsing(fn (MarketplaceItem $record) => filled($record->applicationDetail?->entry_route)),
                Tables\Columns\TextColumn::make('pricing_model')
                    ->label('التسعير')
                    ->badge()
                    ->placeholder('غير محدَّد'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(['application' => 'تطبيق']),
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
            'index' => Pages\ListMarketplaceItems::route('/'),
            'create' => Pages\CreateMarketplaceItem::route('/create'),
            'edit' => Pages\EditMarketplaceItem::route('/{record}/edit'),
        ];
    }
}
