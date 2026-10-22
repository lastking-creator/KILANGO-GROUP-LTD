<?php

return [
    // Shown next to amounts on receipts. Leave empty for none, or set e.g. TZS or KES.
    'currency' => env('POS_CURRENCY', ''),

    'receipt_footer' => env('POS_RECEIPT_FOOTER', 'Thank you for shopping with us.'),
];
