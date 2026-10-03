<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace public home variant
    |--------------------------------------------------------------------------
    |
    | glass    → Glassmorphism home aligned with Core visitor brand (default)
    | classic  → previous dark-hero homepage (fully reversible)
    |
    | Switch via MARKETPLACE_HOME_VARIANT without deleting either view.
    |
    */
    'home_variant' => env('MARKETPLACE_HOME_VARIANT', 'glass'),

];
