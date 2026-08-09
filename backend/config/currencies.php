<?php

// Single source of truth for which currencies an author can price a story
// in - read by StoryManagementController for validation and exposed to the
// frontend via GET /currencies so the price-per-currency form always matches
// what the backend will actually accept, with no hardcoded list to keep in
// sync on both sides.
//
// USD/GBP/NGN were the original three. The rest were added for their reader
// base on serialized/genre fiction platforms specifically - Wattpad traffic
// and app-store data consistently place the Philippines, India, Indonesia,
// and Canada among the top few markets for exactly this kind of reading (see
// e.g. Similarweb's wattpad.com country breakdown), which is the audience
// this app is for.
return [
    'USD' => ['label' => 'US Dollar', 'symbol' => '$'],
    'GBP' => ['label' => 'British Pound', 'symbol' => '£'],
    'NGN' => ['label' => 'Nigerian Naira', 'symbol' => '₦'],
    'PHP' => ['label' => 'Philippine Peso', 'symbol' => '₱'],
    'INR' => ['label' => 'Indian Rupee', 'symbol' => '₹'],
    'IDR' => ['label' => 'Indonesian Rupiah', 'symbol' => 'Rp'],
    'CAD' => ['label' => 'Canadian Dollar', 'symbol' => 'CA$'],
];
