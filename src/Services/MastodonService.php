<?php

namespace App\Services;

use App\Entity\Video;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MastodonService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publishToMastodon(Video $video, string $username): void
    {
        $instanceUrl = rtrim($_ENV['MASTODON_INSTANCE_URL'] ?? '', '/');
        $accessToken = $_ENV['MASTODON_ACCESS_TOKEN'] ?? '';

        if ('' === $instanceUrl || '' === $accessToken) {
            return;
        }

        $identifier = $video->getIdentifier();
        $channel = $video->getChannel()->getName();
        $title = $video->getTitle();

        $message = "@$username vous recommande $title sur $channel\n";
        $message .= "https://www.youtube.com/watch?v=$identifier\n";
        $message .= "#Youtube #Polfr #Politique";

        $response = $this->httpClient->request('POST', $instanceUrl . '/api/v1/statuses', [
            'auth_bearer' => $accessToken,
            'body' => ['status' => $message],
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->error('Mastodon rejected the status', [
                'status' => $statusCode,
                'response' => $response->getContent(false),
            ]);
        }
    }
}
