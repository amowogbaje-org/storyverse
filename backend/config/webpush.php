<?php

return [
    // Generate a pair once per environment with:
    //   php artisan tinker --execute="print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
    // Public key also goes in the frontend's VITE_VAPID_PUBLIC_KEY (frontend/.env) -
    // it's meant to be public, that's the whole point of the "public" key.
    // Private key is backend-only, never ships to the frontend.
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    // mailto: address the push service can contact if this server is misbehaving.
    'subject' => env('VAPID_SUBJECT', 'mailto:support@storyverse.local'),
];
