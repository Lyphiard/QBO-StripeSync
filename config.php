<?php

return [
    'debug' => true,
    'qbo' => [
        'oauth2' => [
            'clientId' => '*** QBO Client ID ***',
            'clientSecret' => '*** QBO Client Secret ***',
            'realmId' => '*** QBO Realm ID ***',
            'baseUrl' => 'Production',
        ],
        'data' => [
            'paymentMethod' => [
                'name' => 'Stripe',
                'value' => 5,
            ],
            'item' => [
                'name' => 'Stripe Sales',
                'value' => 5,
            ],
            'refundItem' => [
                'name' => 'Stripe Refunds',
                'value' => 6
            ],
            'vendor' => [
                'name' => 'Stripe, Inc.',
                'value' => 4,
                'type' => 'vendor'
            ],
            'accounts' => [
                'depositBank' => [
                    'name' => 'Checking Account',
                    'value' => 39
                ],
                'stripeBank' => [
                    'name' => 'Stripe Bank',
                    'value' => 41
                ],
                'stripeFees' => [
                    'name' => 'Stripe Fees',
                    'value' => 52
                ],
                'undepositedFunds' => [
                    'name' => 'Undeposited Funds',
                    'value' => 36
                ],
                'accountsPayable' => [
                    'name' => 'Accounts Payable (A/P)',
                    'value' => 45
                ]
            ]
        ]
    ],
    'stripe' => [
        'secretKey' => '*** Stripe Secret Key ***'
    ]
];
