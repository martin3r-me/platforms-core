<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Livewire\Login;
use Platform\Core\Livewire\Register;
use Platform\Core\Livewire\Dashboard;

Route::get('/login', Login::class)->name('login');
Route::get('/register', Register::class)->name('register');

Route::get('/', Dashboard::class)->name('platform.dashboard');