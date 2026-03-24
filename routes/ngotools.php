<?php

use Illuminate\Support\Facades\Route;

Route::get('.well-known/ngotools.json', function () {
    $path = base_path('.well-known/ngotools.json');

    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, ['Content-Type' => 'application/json']);
});

Route::get('health', fn () => response()->json(['status' => 'ok']));
