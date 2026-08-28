<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use App\Services\MembershipService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Phase OI — كل تعديل/حذف يمر عبر App\Services\MembershipService حصرًا (لا
 * Model::update()/delete() مباشر) — يضمن Last Owner Rule وAuthorization
 * بمعزل عن أي واجهة. راجع docs/phase-oi-owner-integrity-implementation-specification.md.
 *
 * Security Hardening Pass — CreateAction أصبحت أيضًا مُلفَّة بـusing() تستدعي
 * MembershipService::add() (كانت الفجوة الحرجة: Membership::create() مباشر
 * بلا Authorization ولا Audit). راجع
 * docs/platform-authorization-hardening-completion-report.md.
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'الأعضاء';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('role')
                    ->label('الدور')
                    ->options(MembershipRole::options())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('role')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم'),
                Tables\Columns\TextColumn::make('role')
                    ->label('الدور')
                    ->badge()
                    ->formatStateUsing(fn (MembershipRole $state) => $state->label()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): Membership {
                        try {
                            return app(MembershipService::class)->add(
                                Auth::user(),
                                $this->getOwnerRecord(),
                                User::findOrFail($data['user_id']),
                                MembershipRole::from($data['role']),
                            );
                        } catch (InvalidArgumentException|AuthorizationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('تعذّر تنفيذ العملية')
                                ->body($e->getMessage())
                                ->send();

                            throw new Halt;
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (Membership $record, array $data) {
                        $this->runGuarded(function () use ($record, $data) {
                            app(MembershipService::class)->changeRole(
                                Auth::user(),
                                $record,
                                MembershipRole::from($data['role']),
                            );
                        });

                        return $record;
                    }),
                Tables\Actions\Action::make('transferOwnership')
                    ->label('نقل الملكية')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Membership $record): bool => $record->role === MembershipRole::Owner)
                    ->form([
                        Forms\Components\Select::make('to_membership_id')
                            ->label('المالك الجديد')
                            ->options(fn (Membership $record) => Membership::query()
                                ->where('organization_id', $record->organization_id)
                                ->where('id', '!=', $record->id)
                                ->with('user')
                                ->get()
                                ->mapWithKeys(fn (Membership $m) => [$m->id => "{$m->user->name} ({$m->role->label()})"]))
                            ->required()
                            ->helperText('يجب أن يكون عضوًا حاليًا بنفس المؤسسة.'),
                        Forms\Components\Select::make('demote_from_to')
                            ->label('الدور الجديد للمالك الحالي بعد النقل')
                            ->options(MembershipRole::options())
                            ->default(MembershipRole::Admin->value)
                            ->required(),
                    ])
                    ->action(function (Membership $record, array $data) {
                        $this->runGuarded(function () use ($record, $data) {
                            $to = Membership::findOrFail($data['to_membership_id']);

                            app(MembershipService::class)->transferOwnership(
                                Auth::user(),
                                $record,
                                $to,
                                MembershipRole::from($data['demote_from_to']),
                            );
                        });
                    }),
                Tables\Actions\DeleteAction::make()
                    ->using(function (Membership $record) {
                        $this->runGuarded(function () use ($record) {
                            app(MembershipService::class)->remove(Auth::user(), $record);
                        });
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            foreach ($records as $record) {
                                $this->runGuarded(function () use ($record) {
                                    app(MembershipService::class)->remove(Auth::user(), $record);
                                }, haltOnFailure: false);
                            }
                        }),
                ]),
            ]);
    }

    /**
     * كل استدعاء لـMembershipService من هذي الشاشة يمر من هنا — يحوّل رفض
     * Last Owner Rule أو Authorization لإشعار واضح بدل صفحة عطل خام.
     * haltOnFailure=false تُستخدَم بالحذف الجماعي فقط (سجل مرفوض واحد لا
     * يمنع إكمال باقي السجلات بنفس الدفعة).
     */
    private function runGuarded(\Closure $callback, bool $haltOnFailure = true): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException|AuthorizationException $e) {
            Notification::make()
                ->danger()
                ->title('تعذّر تنفيذ العملية')
                ->body($e->getMessage())
                ->send();

            if ($haltOnFailure) {
                throw new Halt;
            }
        } catch (\Throwable $e) {
            // تضارب تزامني حقيقي (قفل قاعدة بيانات، إلخ) — نفس الأسلوب
            // المُطبَّق سابقًا بـOrganizationSeatController::assign() لنفس
            // فئة المشكلة (Phase 2B): رسالة واضحة بدل صفحة عطل خام، لا
            // نبتلع الخطأ بصمت.
            Notification::make()
                ->danger()
                ->title('تعذّر تنفيذ العملية — حاول مرة أخرى')
                ->body('حدث تعارض تزامني أثناء التنفيذ (مثلًا فعل آخر بنفس اللحظة). أعد المحاولة.')
                ->send();

            if ($haltOnFailure) {
                throw new Halt;
            }
        }
    }
}
