<?php

return [

    'analytics' => [
        'tracking_id' => env('TRACKING_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Brevo, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN', ''),
        'secret' => env('MAILGUN_SECRET', ''),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'webhook_signing_key' => env('MAILGUN_WEBHOOK_SIGNING_KEY', ''),
        'scheme' => 'https',
        'from' => [
            'address' => env('MAILGUN_FROM_ADDRESS', ''),
            'name' => env('MAILGUN_FROM_NAME', ''),
        ],
    ],

    'brevo' => [
        'secret' => env('BREVO_SECRET', ''),
    ],

    'postmark' => [
        'token' => env('POSTMARK_SECRET', ''),
    ],

    'postmark-outlook' => [
        'token' => env('POSTMARK_OUTLOOK_SECRET', ''),
        'from' => [
            'address' => env('POSTMARK_OUTLOOK_FROM_ADDRESS', '')
        ],
    ],

    'postmark-broadcast' => [
        'token' => env('POSTMARK_BROADCAST_SECRET', ''),
        'from' => [
            'address' => env('POSTMARK_BROADCAST_FROM_ADDRESS', 'community@invoiceninja.com')
        ],
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],

    'mindee' => [
        'api_key' => env('MINDEE_API_KEY'),
        'daily_limit' => env('MINDEE_DAILY_LIMIT', 100),
        'monthly_limit' => env('MINDEE_MONTHLY_LIMIT', 250),
        'account_daily_limit' => env('MINDEE_ACCOUNT_DAILY_LIMIT', 0),
        'account_monthly_limit' => env('MINDEE_ACCOUNT_MONTHLY_LIMIT', 0),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'gmail' => [
        'token' => '',
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_OAUTH_REDIRECT'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_OAUTH_REDIRECT'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_OAUTH_REDIRECT'),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_OAUTH_REDIRECT'),
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_OAUTH_REDIRECT'),
    ],

    'bitbucket' => [
        'client_id' => env('BITBUCKET_CLIENT_ID'),
        'client_secret' => env('BITBUCKET_CLIENT_SECRET'),
        'redirect' => env('BITBUCKET_OAUTH_REDIRECT'),
    ],

    'tax' => [
        'zip_tax' => [
            'key' => env('ZIP_TAX_KEY', false),
        ],
    ],

    'chorus' => [
        'client_id' => env('CHORUS_CLIENT_ID', false),
        'secret' => env('CHORUS_SECRET', false),
    ],
    'gocardless' => [
        'client_id' => env('GOCARDLESS_CLIENT_ID', null),
        'client_secret' => env('GOCARDLESS_CLIENT_SECRET', null),
        'environment' => env('GOCARDLESS_ENVIRONMENT', 'production'),
        'redirect_uri' => env('GOCARDLESS_REDIRECT_URI', 'https://invoicing.co/gocardless/oauth/connect/confirm'),
        'testing_company' => env('GOCARDLESS_TESTING_COMPANY', null),
    ],
    'quickbooks' => [
        'client_id' => env('QUICKBOOKS_CLIENT_ID', false),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET', false),
        'debug' => env('APP_DEBUG',false)
    ],
    'quickbooks_webhook' => [
        'verifier_token' => env('QUICKBOOKS_VERIFIER_TOKEN', false),
    ],
];
