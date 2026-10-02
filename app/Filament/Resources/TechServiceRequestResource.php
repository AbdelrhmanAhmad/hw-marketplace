<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TechServiceRequestResource\Pages;
use App\Models\TechServiceRequest;
use App\Services\TechServiceRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * بوابة التقنية — طلبات "السلة" الواردة من العملاء المحتملين. لا Create/Edit
 * (تصل من النموذج العام، لا من Filament) — فقط عرض ومتابعة الحالة، نفس نمط
 * ArticleAuthorResource. كل تحويل حالة يمر عبر TechServiceRequestService (BR-013).
 */
class TechServiceRequestResource extends Resource
{
    protected static ?string $model = TechServiceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'بوابة التقنية';

    protected static ?string $navigationLabel = 'طلبات واردة';

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات الواردة';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('name')->label('الاسم')->content(fn (TechServiceRequest $record) => $record->name),
            Forms\Components\Placeholder::make('email')->label('البريد')->content(fn (TechServiceRequest $record) => $record->email),
            Forms\Components\Placeholder::make('phone')->label('الجوال')->content(fn (TechServiceRequest $record) => $record->phone ?? '—'),
            Forms\Components\Placeholder::make('message')->label('الرسالة')->content(fn (TechServiceRequest $record) => $record->message ?? '—')->columnSpanFull(),
            Forms\Components\Placeholder::make('services')
                ->label('الخدمات المطلوبة')
                ->content(fn (TechServiceRequest $record) => $record->items->map(fn ($item) => $item->service?->title)->filter()->implode('، '))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('البريد')->searchable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('عدد الخدمات')
                    ->getStateUsing(fn (TechServiceRequest $record) => $record->items()->count()),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'new' => 'جديد',
                        'contacted' => 'تم التواصل',
                        'closed' => 'مغلق',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'contacted' => 'info',
                        'closed' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الطلب')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(['new' => 'جديد', 'contacted' => 'تم التواصل', 'closed' => 'مغلق']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('mark_contacted')
                    ->label('تم التواصل')
                    ->icon('heroicon-o-phone')
                    ->color('info')
                    ->visible(fn (TechServiceRequest $record) => $record->status === 'new')
                    ->action(function (TechServiceRequest $record) {
                        app(TechServiceRequestService::class)->updateStatus($record, 'contacted');
                        Notification::make()->title('حُدِّثت الحالة إلى "تم التواصل"')->success()->send();
                    }),
                Tables\Actions\Action::make('mark_closed')
                    ->label('إغلاق')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TechServiceRequest $record) => $record->status !== 'closed')
                    ->requiresConfirmation()
                    ->action(function (TechServiceRequest $record) {
                        app(TechServiceRequestService::class)->updateStatus($record, 'closed');
                        Notification::make()->title('أُغلِق الطلب')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechServiceRequests::route('/'),
            'view' => Pages\ViewTechServiceRequest::route('/{record}'),
        ];
    }
}
