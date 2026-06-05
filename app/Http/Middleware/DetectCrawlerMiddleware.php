<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectCrawlerMiddleware
{
    protected array $crawlerUserAgents = [
        // Meta / Facebook / Messenger / Instagram
        'facebookexternalhit',
        'facebookcatalog',
        'Facebot',
        'meta-externalfetcher',  // Messenger user-initiated link fetch
        'meta-externalagent',
        'meta-externalads',
        'meta-webindexer',
        // Other platforms
        'Twitterbot',
        'Slackbot',
        'WhatsApp',
        'TelegramBot',
        'LinkedInBot',
        'Pinterest',
        'Googlebot',
        'bingbot',
        'Discordbot',
        'Applebot',
        // Zalo
        'zalo',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = $request->userAgent() ?? '';
        $isCrawler = $this->isCrawler($userAgent);

        $request->attributes->set('is_crawler', $isCrawler);

        return $next($request);
    }

    protected function isCrawler(string $userAgent): bool
    {
        $userAgent = strtolower($userAgent);

        foreach ($this->crawlerUserAgents as $bot) {
            if (str_contains($userAgent, strtolower($bot))) {
                return true;
            }
        }

        return false;
    }
}
