<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Services\ArticleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * بوابة المقالات — مراجعة/نشر المقالات. نفس نمط ArticleAuthorResource
 * (BR-013، إجراءات تستدعي ArticleService حصرًا). لا Create/Edit من هنا —
 * المقالات تُكتَب من لوحة المؤلف العامة (apps/articles)، هذي الصفحة مراجعة فقط.
 */
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'بوابة المقالات';

    protected static ?string $navigationLabel = 'المقالات';

    protected static ?string $modelLabel = 'مقال';

    protected static ?string $pluralModelLabel = 'المقالات';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('author')->label('المؤلف')->content(fn (Article $record) => $record->author?->user?->name),
            Forms\Components\Placeholder::make('title')->label('العنوان')->content(fn (Article $record) => $record->title),
            Forms\Components\Placeholder::make('excerpt')->label('المقتطف')->content(fn (Article $record) => $record->excerpt)->columnSpanFull(),
            Forms\Components\Placeholder::make('body')->label('المحتوى')->content(fn (Article $record) => $record->body)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('author.user.name')->label('المؤلف')->searchable(),
                Tables\Columns\TextColumn::make('category.name')->label('التصنيف'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'draft' => 'مسودة',
                        'pending_review' => 'بانتظار المراجعة',
                        'published' => 'منشور',
                        'rejected' => 'مرفوض',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'pending_review' => 'warning',
                        'published' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('published_at')->label('تاريخ النشر')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(['draft' => 'مسودة', 'pending_review' => 'بانتظار المراجعة', 'published' => 'منشور', 'rejected' => 'مرفوض']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('نشر')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Article $record) => $record->status === 'pending_review')
                    ->requiresConfirmation()
                    ->action(function (Article $record) {
                        app(ArticleService::class)->approveArticle(Auth::user(), $record);
                        Notification::make()->title('نُشر المقال')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Article $record) => $record->status === 'pending_review')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('سبب الرفض')->required(),
                    ])
                    ->action(function (Article $record, array $data) {
                        app(ArticleService::class)->rejectArticle(Auth::user(), $record, $data['reason']);
                        Notification::make()->title('رُفض المقال')->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'view' => Pages\ViewArticle::route('/{record}'),
        ];
    }
}
