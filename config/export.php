<?php

return [

    'pdf' => [
        // Path to your node binary
        'node_binary' => env('NODE_BINARY', 'node'),

        // Node script that runs Paged.js (we’ll create a stub next)
        'paged_script' => base_path('resources/export/paged-render.mjs'),

        // Ghostscript binary for PDF/X conversion
        'ghostscript_binary' => env('GHOSTSCRIPT_BINARY', 'gs'),

        // Disk where final PDFs are stored (S3/Supabase/etc)
        'disk' => env('EXPORT_DISK', 's3'),

        // Folder inside the disk
        'path_prefix' => 'exports/pdf',

        // Temp directory (local)
        'tmp_dir' => storage_path('app/exports/tmp'),
    ],

];
