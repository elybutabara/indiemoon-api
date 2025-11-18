<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-upload', function () {    

    try {
        Storage::disk('s3')->put('new-folder/hello2.txt', 'Hello from Laravel via Supabase S3 new file');

        $url = Storage::disk('s3')->temporaryUrl('new-folder/hello2.txt', now()->addMinutes(5));

        return response()->json(['url' => $url], 200);
    } catch (\Throwable $e) {
        report($e);
        return response()->json([
            'error' => $e->getMessage(),
            'class' => get_class($e),
        ], 500);
    }
});

