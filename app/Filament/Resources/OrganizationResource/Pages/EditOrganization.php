<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\OrganizationLifecycleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Phase OL — Hard Delete أُلغي نهائيًا من الـDomain (لا DeleteAction هنا
 * إطلاقًا). Archive/Restore فقط، عبر OrganizationLifecycleService حصرًا.
 */
class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('archive')
                ->label('أرشفة')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('يُلغي كل اشتراك مؤسسي نشط، يُحرِّر كل مقعد، ويُبطِل كل وصول — فورًا. لا يحذف أي بيانات، قابلة للاستعادة لاحقًا.')
                ->visible(fn (Organization $record): bool => ! $record->isArchived())
                ->action(function (Organization $record) {
                    $this->runGuarded(fn () => app(OrganizationLifecycleService::class)->archive(Auth::user(), $record));
                }),
            Actions\Action::make('restore')
                ->label('استعادة')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn (Organization $record): bool => $record->isArchived())
                ->action(function (Organization $record) {
                    $this->runGuarded(fn () => app(OrganizationLifecycleService::class)->restore(Auth::user(), $record));
                }),
        ];
    }

    private function runGuarded(\Closure $callback): void
    {
        try {
            $callback();
        } catch (\InvalidArgumentException|AuthorizationException $e) {
            Notification::make()->danger()->title('تعذّر تنفيذ العملية')->body($e->getMessage())->send();
            throw new Halt;
        }
    }
}
