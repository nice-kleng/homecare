<?php
// config/homecare.php
return [
    'checkin_tolerance_meters' => env('CHECKIN_TOLERANCE_METERS', 500),
];

// config/whatsapp.php
return [
    'provider' => env('WA_PROVIDER', 'fonnte'),
    'endpoint' => env('WA_ENDPOINT', 'https://api.fonnte.com/send'),
    'token'    => env('WA_TOKEN', ''),
];
