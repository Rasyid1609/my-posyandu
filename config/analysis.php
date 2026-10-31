<?php

return [
    'enabled' => (bool) env('POSYANDU_AI_ENABLED', false),
    'ocr_enabled' => (bool) env('POSYANDU_OCR_ENABLED', false),
    'processing_approved' => (bool) env('POSYANDU_PROVIDER_PROCESSING_APPROVED', false),
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_MODEL', ''),
    'ocr_model' => env('OPENAI_OCR_MODEL', ''),
    'minimum_cell_size' => max(5, (int) env('POSYANDU_AI_MINIMUM_CELL_SIZE', 5)),
    'daily_limit' => max(1, min(20, (int) env('POSYANDU_AI_DAILY_LIMIT', 5))),
];
