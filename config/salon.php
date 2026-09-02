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
        'footer_text' => 'Powered by 2iT',
        'logo_path' => '',
        'app_url' => env('APP_URL', 'http://localhost'),
        'whatsapp_template' => "{greeting} {customer_name}! 🙏\nThank you for visiting {salon_name}.\n\nYour invoice {invoice_number} for ₹{total} is here:\n{invoice_link}\n\nWe look forward to seeing you again!",
    ],

    'expense_categories' => ['Products', 'Rent', 'Salary', 'Electricity', 'Tea/Snacks', 'Misc'],

    'payment_modes' => ['cash', 'upi', 'card', 'other'],

];
