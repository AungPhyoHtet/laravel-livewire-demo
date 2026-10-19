<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/meetings/{meeting}', 'pages::meeting-notes')->name('meetings.show');

Route::livewire('/profiles/{profile}', 'pages::profile-settings')->name('profiles.edit');

Route::livewire('/products', 'pages::products')->name('products.index');

Route::livewire('/products/{product}/tags', 'pages::product-tags')->name('products.tags');

Route::livewire('/posts', 'pages::posts')->name('posts.index');

Route::livewire('/posts/{post:slug}', 'pages::post-show')->name('posts.show');

Route::livewire('/users/{user}/dashboard', 'pages::dashboard')->name('users.dashboard');
