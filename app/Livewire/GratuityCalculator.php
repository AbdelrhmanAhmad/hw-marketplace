<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GratuityCalculator extends Component
{
    public ?float $salary = null;

    public ?float $years = null;

    public string $endReason = 'employer';

    #[Computed]
    public function fullGratuity(): float
    {
        if (! $this->salary || ! $this->years || $this->years <= 0) {
            return 0;
        }

        $halfMonth = $this->salary / 2;
        $fullMonth = $this->salary;

        if ($this->years <= 5) {
            return round($halfMonth * $this->years, 2);
        }

        return round(($halfMonth * 5) + ($fullMonth * ($this->years - 5)), 2);
    }

    #[Computed]
    public function resignationFactor(): float
    {
        return match (true) {
            $this->years < 2 => 0,
            $this->years < 5 => 1 / 3,
            $this->years < 10 => 2 / 3,
            default => 1,
        };
    }

    #[Computed]
    public function result(): float
    {
        if ($this->endReason === 'resignation') {
            return round($this->fullGratuity * $this->resignationFactor, 2);
        }

        return $this->fullGratuity;
    }

    public function render()
    {
        return view('livewire.gratuity-calculator');
    }
}
