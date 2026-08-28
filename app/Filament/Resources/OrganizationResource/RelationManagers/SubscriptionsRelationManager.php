<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Models\MarketplaceItem;
use App\Models\Subscription;
use App\Services\OrganizationSubscriptionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Phase 2B — كل إنشاء/تعديل يمر عبر OrganizationSubscriptionService، لا
 * Model::update() مباشر (تصحيح صريح من المستخدم على مسودة التصميم الأولى).
 * هذي أداة فريق حكم ورقم الإداري (لا واجهة ذاتية للمكتب) — لا Billing خلفها.
 *
 * AD-018 — create()/changeSeatLimit() قد يرفضان الفعل الآن بسبب حالة
 * المؤسسة (مؤرشَفة)، لا فقط Authorization — runGuarded() يحوّل كلا النوعين
 * لإشعار واضح، نفس نمط MembershipsRelationManager (Phase OI).
 */
class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'marketplaceSubscriptions';

    protected static ?string $title = 'الاشتراكات المؤسسية';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('marketplace_item_id')
                    ->label('التطبيق')
                    ->options(MarketplaceItem::whereIn('billing_model', ['organization_only', 'both'])->pluck('name', 'id'))
                    ->required()
                    ->disabledOn('edit'),
                Forms\Components\TextInput::make('plan_name')
                    ->label('اسم الخطة')
                    ->default('Professional')
                    ->required()
                    ->visibleOn('create'),
                Forms\Components\TextInput::make('seat_limit')
                    ->label('عدد المقاعد')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('marketplaceItem.name')
                    ->label('التطبيق'),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('الخطة'),
                Tables\Columns\TextColumn::make('plan.seat_limit')
                    ->label('حد المقاعد'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): Model {
                        $organization = $this->getOwnerRecord();
                        $item = MarketplaceItem::findOrFail($data['marketplace_item_id']);

                        try {
                            return app(OrganizationSubscriptionService::class)->create(
                                Auth::user(),
                                $organization,
                                $item,
                                $data['plan_name'],
                                (int) $data['seat_limit'],
                            );
                        } catch (InvalidArgumentException|AuthorizationException $e) {
                            $this->notifyRejection($e->getMessage());
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (Subscription $record, array $data): Subscription {
                        try {
                            app(OrganizationSubscriptionService::class)->changeSeatLimit(Auth::user(), $record, (int) $data['seat_limit']);
                        } catch (InvalidArgumentException|AuthorizationException $e) {
                            $this->notifyRejection($e->getMessage());
                        }

                        return $record;
                    }),
            ]);
    }

    private function notifyRejection(string $message): never
    {
        Notification::make()
            ->danger()
            ->title('تعذّر تنفيذ العملية')
            ->body($message)
            ->send();

        throw new Halt;
    }
}
