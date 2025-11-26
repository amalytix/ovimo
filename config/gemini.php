<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    // gemini-2.5-flash-image is faster and more reliable
    // gemini-3-pro-image-preview is higher quality but may timeout
    'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
    'request_timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 180),
];
