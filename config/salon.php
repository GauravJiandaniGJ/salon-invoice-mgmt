<?php

return [

    /*
    | WhatsApp delivery driver: "wame" (click-to-chat link, phase 1)
    | or "cloud" (WhatsApp Cloud API, phase 2 – not built yet).
    */
    'whatsapp_driver' => env('WHATSAPP_DRIVER', 'wame'),

    'currency' => 'INR',
    'currency_symbol' => '₹',
    'country_code' => '91',

    /*
    | Defaults used by the SettingsSeeder. Live values live in the
    | `settings` table and are read via Setting::get().
    */
    'defaults' => [
        'salon_name' => 'Wow Salon',
        'salon_tagline' => 'The Unisex Salon',
        'salon_address' => '',
        'salon_phone' => '',
        'salon_whatsapp_number' => '',
        'invoice_prefix' => 'WS',
        'tax_rate' => '0',
        'footer_text' => 'Powered by TodoIT',
        'logo_path' => '',
        'app_url' => env('APP_URL', 'http://localhost'),
        'whatsapp_template' => "{greeting} {customer_name} 🙏\nThank you for visiting {salon_name}. Your invoice {invoice_number} for ₹{total} is ready:\n{invoice_link}\n\nSee you again soon!",
        'brand_color' => '#C9A24B',
        'whatsapp_driver' => 'wame', // wame | cloud (setting overrides config value below)
        'whatsapp_cloud_phone_id' => '',
        'whatsapp_cloud_token' => '',
        'whatsapp_cloud_template' => 'invoice_ready',
    ],

    /* Technology partner branding shown in the header, invoices and WhatsApp messages. */
    'powered_by' => [
        'name' => 'TodoIT',
        'url' => 'https://todoitservices.com',
        'label' => 'Powered by TodoIT',
    ],

    'expense_categories' => ['Products', 'Rent', 'Salary', 'Electricity', 'Tea/Snacks', 'Misc'],

    'payment_modes' => ['cash', 'upi', 'card', 'other'],

];
