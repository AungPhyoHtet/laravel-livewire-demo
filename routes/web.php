<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/meetings/{meeting}', 'pages::meeting-notes')->name('meetings.show');

Route::livewire('/profiles/{profile}', 'pages::profile-settings')->name('profiles.edit');
