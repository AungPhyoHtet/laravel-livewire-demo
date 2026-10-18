<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/meetings/{meeting}', 'pages::meeting-notes')->name('meetings.show');

Route::livewire('/profiles/{profile}', 'pages::profile-settings')->name('profiles.edit');

Route::livewire('/products', 'pages::products')->name('products.index');

Route::livewire('/products/{product}/tags', 'pages::product-tags')->name('products.tags');
