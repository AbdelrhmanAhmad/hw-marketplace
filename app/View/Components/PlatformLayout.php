<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PlatformLayout extends Component
{
    public function __construct(
        public string $bodyClass = 'font-sans antialiased bg-white',
    ) {}

    public function render(): View
    {
        return view('layouts.platform');
    }
}
