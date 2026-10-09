<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    app()->setLocale('zh_TW');

    return view('landing');
})->name('landing');

Route::get('/en', function () {
    app()->setLocale('en');

    return view('landing');
})->name('landing.en');
