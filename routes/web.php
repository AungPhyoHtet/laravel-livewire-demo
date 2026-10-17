<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/meetings/{meeting}', 'pages::meeting-notes')->name('meetings.show');
