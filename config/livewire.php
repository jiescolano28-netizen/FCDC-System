<?php

return [
    // Keep Livewire's upload defaults while allowing PDF temporary previews for
    // the Reworking Cost Record supporting documents.
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => null,
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
            'pdf',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
