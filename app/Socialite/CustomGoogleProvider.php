<?php

namespace App\Socialite;

use Laravel\Socialite\Two\GoogleProvider;
use GuzzleHttp\Client;

class CustomGoogleProvider extends GoogleProvider
{
    protected function getHttpClient()
    {
        if (is_null($this->httpClient)) {
            $caCertPath = $this->getCaCertPath();
            $options = $this->guzzle;

            if ($caCertPath && file_exists($caCertPath)) {
                $options['verify'] = $caCertPath;
            }

            $this->httpClient = new Client($options);
        }

        return $this->httpClient;
    }

    protected function getCaCertPath()
    {
        // Check environment variable
        $envPath = env('CURL_CERT_PATH');
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        // Check common Windows paths
        $commonPaths = [
            'C:\Users\Admin\cacert.pem',
            'C:\Program Files\PHP\extras\ssl\cacert.pem',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
