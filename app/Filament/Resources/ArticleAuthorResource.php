<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleAuthorResource\Pages;
use App\Models\ArticleAuthor;
use App\Services\ArticleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * بوابة المقالات — مراجعة/موافقة طلبات التأليف. أول نمط إجراء
 * Approve/Reject بلوحة Filament بهذا المشروع — كل تحويل حالة يمر عبر
 * ArticleService (BR-013)، لا Model::update() مباشر من هنا. الحارس الوحيد
 * `is_platform_staff` (canAccessPanel) — بلا Policy إضافية.
 */
class ArticleAuthorResource extends Resource
{
    protected static ?string $model = ArticleAuthor::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'بوابة المقالات';

    protected static ?string $navigationLabel = 'طلبات التأليف';

    protected static ?string $modelLabel = 'طلب تأليف';

    protected static ?string $pluralModelLabel = 'طلبات التأليف';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('user.name')->label('المستخدم')->content(fn (ArticleAuthor $record) => $record->user?->name),
            Forms\Components\Placeholder::make('user.email')->label('البريد')->content(fn (ArticleAuthor $record) => $record->user?->email),
            Forms\Components\Placeholder::make('expertise')->label('التخصص')->content(fn (ArticleAuthor $record) => $record->expertise),
            Forms\Components\Placeholder::make('bio')->label('النبذة')->content(fn (ArticleAuthor $record) => $record->bio)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->searchable(),
                Tables\Columns\TextColumn::make('expertise')->label('التخصص')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'بانتظار المراجعة',
                        'approved' => 'مُوافَق عليه',
                        'rejected' => 'مرفوض',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الطلب')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('reviewedBy.name')->label('راجعه')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(['pending' => 'بانتظار المراجعة', 'approved' => 'مُوافَق عليه', 'rejected' => 'مرفوض']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ArticleAuthor $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (ArticleAuthor $record) {
                        app(ArticleService::class)->approveAuthor(Auth::user(), $record);
                        Notification::make()->title('تمت الموافقة على المؤلف')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ArticleAuthor $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('سبب الرفض')->required(),
                    ])
                    ->action(function (ArticleAuthor $record, array $data) {
                        app(ArticleService::class)->rejectAuthor(Auth::user(), $record, $data['reason']);
                        Notification::make()->title('رُفض طلب التأليف')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticleAuthors::route('/'),
            'view' => Pages\ViewArticleAuthor::route('/{record}'),
        ];
    }
}
