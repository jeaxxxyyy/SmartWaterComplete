<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.landing');
Route::view('/login', 'pages.login')->name('login.page');
Route::view('/dashboard', 'pages.dashboard')->name('dashboard.page');
Route::view('/admin', 'pages.admin')->name('admin.page');
