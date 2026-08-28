<?php

namespace App\Filament\Resources;

use App\Enums\MembershipRole;
use App\Filament\Resources\OrganizationResource\Pages;
use App\Filament\Resources\OrganizationResource\RelationManagers;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\OrganizationLifecycleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'المكاتب والمؤسسات';

    protected static ?string $modelLabel = 'مكتب/مؤسسة';

    protected static ?string $pluralModelLabel = 'المكاتب والمؤسسات';

    protected static ?string $navigationGroup = 'Core Platform';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('النوع')
                    ->options([
                        'law_firm' => 'مكتب محاماة',
                        'accounting_firm' => 'مكتب محاسبة',
                        'individual' => 'فردي',
                    ])
                    ->default('individual')
                    ->required(),
                Forms\Components\Select::make('owner_id')
                    ->label('المالك (Legacy — للعرض التاريخي فقط)')
                    ->relationship('owner', 'name')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('غير مرتبط بالصلاحيات — لا يُعتمَد عليه لتحديد من يدير المؤسسة فعليًا. راجع "المالك الفعلي (Membership)" أدناه.'),
                Forms\Components\Placeholder::make('actual_owners')
                    ->label('المالك الفعلي (Membership.role=Owner)')
                    ->content(fn (?Organization $record): string => self::actualOwnersLabel($record)),
            ]);
    }

    /**
     * Phase OI — Owner Integrity Retirement Stage 1: تنبيه بصري فقط، لا حجب.
     * المصدر الوحيد الحقيقي للصلاحية Membership.role=Owner — owner_id عرضي بحت.
     */
    private static function actualOwnersLabel(?Organization $record): string
    {
        if (! $record) {
            return '—';
        }

        $owners = Membership::query()
            ->where('organization_id', $record->id)
            ->where('role', MembershipRole::Owner)
            ->with('user')
            ->get();

        if ($owners->isEmpty()) {
            return '⚠️ لا يوجد Owner فعلي بهذي المؤسسة إطلاقًا';
        }

        $names = $owners->pluck('user.name')->implode('، ');

        $ownerIdMatches = $record->owner_id && $owners->contains('user_id', $record->owner_id);

        return $names.($ownerIdMatches ? '' : ' — ⚠️ لا يطابق حقل "المالك" القديم أعلاه');
    }

    private static function ownerIdMatchesMembership(Organization $record): bool
    {
        if (! $record->owner_id) {
            return true;
        }

        return Membership::query()
            ->where('organization_id', $record->id)
            ->where('user_id', $record->owner_id)
            ->where('role', MembershipRole::Owner)
            ->exists();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('المالك (Legacy)')
                    ->searchable()
                    ->color(fn (Organization $record) => self::ownerIdMatchesMembership($record) ? null : 'danger')
                    ->tooltip(fn (Organization $record) => self::ownerIdMatchesMembership($record)
                        ? null
                        : 'لا يطابق أي Membership.role=Owner فعلي — راجع تبويب الأعضاء'),
                Tables\Columns\TextColumn::make('memberships_count')
                    ->label('عدد الأعضاء')
                    ->counts('memberships'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'archived' ? 'مؤرشَفة' : 'فعّالة')
                    ->color(fn (string $state) => $state === 'archived' ? 'gray' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُنشئ في')
                    ->dateTime('d F Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('archive')
                    ->label('أرشفة')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('يُلغي كل اشتراك مؤسسي نشط، يُحرِّر كل مقعد، ويُبطِل كل وصول — فورًا. لا يحذف أي بيانات، قابلة للاستعادة لاحقًا.')
                    ->visible(fn (Organization $record): bool => ! $record->isArchived())
                    ->action(fn (Organization $record) => self::runGuarded(
                        fn () => app(OrganizationLifecycleService::class)->archive(Auth::user(), $record)
                    )),
                Tables\Actions\Action::make('restore')
                    ->label('استعادة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (Organization $record): bool => $record->isArchived())
                    ->action(fn (Organization $record) => self::runGuarded(
                        fn () => app(OrganizationLifecycleService::class)->restore(Auth::user(), $record)
                    )),
            ])
            ->bulkActions([]);
    }

    /**
     * Phase OL — Hard Delete أُلغي نهائيًا من الـDomain، لا مسار له هنا حتى
     * بالواجهة. راجع docs/organization-lifecycle-hardening-design.md قسم D.
     */
    private static function runGuarded(\Closure $callback): void
    {
        try {
            $callback();
        } catch (\InvalidArgumentException|AuthorizationException $e) {
            Notification::make()->danger()->title('تعذّر تنفيذ العملية')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MembershipsRelationManager::class,
            RelationManagers\SubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
