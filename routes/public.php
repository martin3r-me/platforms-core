<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Livewire\Public\PublicExtraFieldForm;

Route::get('/form/{token}', PublicExtraFieldForm::class)
    ->name('core.public.extra-field-form');
