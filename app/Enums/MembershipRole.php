<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Partner = 'partner';
    case Lawyer = 'lawyer';
    case Accountant = 'accountant';
    case FinancialConsultant = 'financial_consultant';
    case Trainee = 'trainee';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالك',
            self::Admin => 'مدير',
            self::Partner => 'شريك',
            self::Lawyer => 'محامٍ',
            self::Accountant => 'محاسب',
            self::FinancialConsultant => 'مستشار مالي',
            self::Trainee => 'متدرّب',
            self::Client => 'عميل',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $role) => $role->value, self::cases()),
            array_map(fn (self $role) => $role->label(), self::cases()),
        );
    }
}
