<?php

return [

    'pdf' => [
        // Path to your node binary
        'node_binary' => env('NODE_BINARY', 'node'),

        // Node script that runs Paged.js (we’ll create a stub next)
        'paged_script' => base_path('resources/export/paged-render.mjs'),

        // Ghostscript binary for PDF/X conversion
        'ghostscript_binary' => env('GHOSTSCRIPT_BINARY', 'gs'),

        // Disk where final PDFs are stored
        'disk' => env('EXPORT_DISK', 's3'),

        // Folder inside the disk
        'path_prefix' => 'exports/pdf',

        // Temp directory (local)
        'tmp_dir' => storage_path('app/exports/tmp'),
    ],

    'epub' => [
        // Disk where final EPUBs are stored
        'disk' => env('EXPORT_DISK', 's3'),

        // Folder inside the disk
        'path_prefix' => 'exports/epub',

        // Temp directory (local)
        'tmp_dir' => storage_path('app/exports/tmp'),

        // Binary used to run epubcheck (defaults to `npx`)
        'epubcheck_binary' => env('EPUBCHECK_BINARY', 'npx'),

        // Arguments passed to the epubcheck binary before the file path
        // Default: ["epubcheck"] => `npx epubcheck <file>`
        'epubcheck_args' => ['epubcheck'],
    ],

    'idml' => [
        // Disk where final IDML files are stored
        'disk' => env('EXPORT_DISK', 's3'),

        // Folder inside the disk
        'path_prefix' => 'exports/idml',

        // Temp directory (local)
        'tmp_dir' => storage_path('app/exports/tmp'),
    ],

];
