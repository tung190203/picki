<?php

/**
 * Get the path to the CA certificate bundle for SSL verification.
 * This is needed on Windows where PHP often lacks the CA certificates.
 */
function getCaCertPath()
{
    static $path = null;

    if ($path !== null) {
        return $path;
    }

    // Check environment variable first
    $envPath = env('CURL_CERT_PATH');
    if ($envPath && file_exists($envPath)) {
        $path = $envPath;
        return $path;
    }

    // Check common Windows paths
    $commonPaths = [
        'C:\Users\Admin\cacert.pem',
        base_path('cacert.pem'),
        base_path('extras/cacert.pem'),
        'C:\Program Files\PHP\extras\ssl\cacert.pem',
    ];

    foreach ($commonPaths as $p) {
        if (file_exists($p)) {
            $path = $p;
            return $path;
        }
    }

    // If curl.cainfo is set in php.ini, use it
    $curlCainfo = ini_get('curl.cainfo');
    if ($curlCainfo && file_exists($curlCainfo)) {
        $path = $curlCainfo;
        return $path;
    }

    $path = false;
    return $path;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'guzzle' => array_filter([
            'verify' => getCaCertPath(),
        ]),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'club_report_notification_email' => env('CLUB_REPORT_NOTIFICATION_EMAIL', 'truongvanthang@gmail.com'),
];
