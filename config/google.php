<?php

return [

    'client_id' => env('GOOGLE_CLIENT_ID', ''),

    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),

    'redirect_uri' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'https://mke.rockproviders.com'), '/').'/google/callback'),

    'scopes' => [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/documents',
    ],

];
