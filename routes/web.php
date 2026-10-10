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

Route::get('/code-of-conduct', function () {
    app()->setLocale('zh_TW');

    return view('code-of-conduct');
})->name('conduct');

Route::get('/en/code-of-conduct', function () {
    app()->setLocale('en');

    return view('code-of-conduct');
})->name('conduct.en');

Route::get('/communities', function () {
    app()->setLocale('zh_TW');

    return view('communities');
})->name('communities');

Route::get('/en/communities', function () {
    app()->setLocale('en');

    return view('communities');
})->name('communities.en');
