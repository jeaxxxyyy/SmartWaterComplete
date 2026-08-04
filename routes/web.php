<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/splash', 'splash')->name('splash');
Route::redirect('/landing', '/')->name('landing.page');
Route::view('/login', 'pages.login')->name('login');
Route::view('/household', 'pages.dashboard')->name('household.page');
Route::redirect('/dashboard', '/household')->name('dashboard.page');
Route::view('/admin', 'pages.admin')->name('admin.page');
