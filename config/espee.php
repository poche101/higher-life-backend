<?php

return [
    // 'espee'    = "Pay with Espees" button from the Espees Merchant dashboard (production).
    //              The user pays on Espees; Laravel then confirms it with Espees' Confirm Payment API.
    // 'transfer' = manual: user sends Espees to a code, an admin approves in the panel (fallback).
    // 'fake'     = local testing only (auto-approves payments).
    'driver' => env('ESPEE_DRIVER', 'transfer'),

    // --- Pay with Espees Button (merchant.espees.org -> Payment Integration -> Pay with Espees Button) ----
    // The embed code Espees generates is:
    //   <script src="https://merchant.espees.org/static/public/button.js"></script>
    //   <script>Espeespaybutton("<YOUR BUTTON ID>");</script>
    // The id below is the Higher Life button; override it with ESPEE_BUTTON_ID in .env when you replace the button.
    'button_script' => env('ESPEE_BUTTON_SCRIPT', 'https://merchant.espees.org/static/public/button.js'),
    'button_id' => env('ESPEE_BUTTON_ID', '695bc9cfc64c3fc1adbce8ba3b559465'),

    // These must match the product you created for the button on the Espees Merchant dashboard.
    'product_sku' => env('ESPEE_PRODUCT_SKU', 'HLIFE-MONTHLY-001'),
    'product_name' => env('ESPEE_PRODUCT_NAME', 'Higher Life Monthly Subscription'),

    // --- Confirm Payment API (official docs) ------------------------------------------------------
    'api_base' => env('ESPEE_API_BASE', 'https://api.espees.org'),
    // Developer-console token, sent as the `x-api-key` header. NOT the merchant ID or wallet address.
    // Only used to CONFIRM payments server-side; it never reaches the browser or the app.
    'api_key' => env('ESPEE_API_KEY', ''),

    'currency' => env('ESPEE_CURRENCY', 'ESP'),

    // --- Manual transfer fallback --------------------------------------------------------------
    'pay_url' => env('ESPEE_PAY_URL', ''),
    'kc_pay_url' => env('ESPEE_KC_PAY_URL', ''),
    'transfer_code' => env('ESPEE_TRANSFER_CODE', 'z5project'), // PLACEHOLDER recipient code

    // Disk used for thumbnails (and media, if video/audio are ever turned back on).
    'media_disk' => env('MEDIA_DISK', 'public'),
];
