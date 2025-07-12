<?php

Route::post('/debug/file-upload', function(\Illuminate\Http\Request $request) {
    \Log::info('=== DEBUG ROUTE START ===');
    \Log::info('Request method: ' . $request->method());
    \Log::info('Content type: ' . $request->header('Content-Type'));
    \Log::info('Request input: ', $request->all());
    \Log::info('Request files: ', $request->file());
    \Log::info('Has files check: ', [
        'files' => $request->hasFile('files'),
        'files_array' => $request->hasFile('files[]'),
    ]);
    \Log::info('$_FILES: ', $_FILES ?? []);
    \Log::info('$_POST: ', $_POST ?? []);
    \Log::info('=== DEBUG ROUTE END ===');

    return response()->json([
        'debug' => true,
        'has_files' => $request->hasFile('files'),
        'FILES' => $_FILES ?? [],
        'POST' => $_POST ?? []
    ]);
});
