<?php

return [

    'warehouse' => [
        'bays_per_aisle' => (int) env('EL3KTRON_BAYS_PER_AISLE', 20),
        'aisle_spacing_m' => (float) env('EL3KTRON_AISLE_SPACING_M', 3.0),
        'bay_width_m' => (float) env('EL3KTRON_BAY_WIDTH_M', 1.4),
    ],

    'sync' => [
        // The ERP's catalogue export: sku,ean,name,family_code,price_cents,status
        'catalog_csv' => env('EL3KTRON_CATALOG_CSV', storage_path('app/erp/catalog.csv')),
        // No successful sync for longer than this raises a critical alert.
        'max_age_minutes' => (int) env('EL3KTRON_SYNC_MAX_AGE_MINUTES', 60),
    ],

    'alerts' => [
        // Empty: alerts are only logged and listed by the API.
        'mail_to' => env('EL3KTRON_ALERTS_MAIL_TO'),
    ],

    'orders' => [
        // rules | llm
        'extractor' => env('EL3KTRON_ORDER_EXTRACTOR', 'rules'),
        'sku_pattern' => '/\bE3-\d{5}\b/',
    ],

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('EL3KTRON_AI_MODEL', 'claude-haiku-5-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'timeout_seconds' => 30,
    ],

];
