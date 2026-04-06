<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->get('/exports/{path}', function (string $path) {
    if (! Str::startsWith($path, 'exports/') || Str::contains($path, '..')) {
        abort(404);
    }

    if (! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    $name = request()->query('name');
    $downloadName = is_string($name) && $name !== '' ? $name : basename($path);

    return Storage::disk('public')->download($path, $downloadName, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
})->where('path', '.*')->name('exports.download');
