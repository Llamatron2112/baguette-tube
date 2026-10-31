<?php

namespace App\Services;

use App\Entity\Video;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\BlueskyPostService;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\HttpComponentsManager;
use potibm\Bluesky\Media\BlobMediaSource;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BlueskyService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function publishToBluesky(Video $video, string $username): void
    {
        if (!isset($_ENV['BLUESKY_HANDLE'], $_ENV['BLUESKY_APP_PASSWORD'])) {
            return;
        }

        $identifier = $video->getIdentifier();
        $channel = $video->getChannel()->getName();
        $title = $video->getTitle();

        $api = new BlueskyApi(
            $_ENV['BLUESKY_HANDLE'],
            $_ENV['BLUESKY_APP_PASSWORD'],
            new HttpComponentsManager(),
            'https://bsky.social/',
        );

        $postService = new BlueskyPostService($api);

        $text = "@$username vous recommande\n";
        $text .= "$channel\n";
        $text .= "$title\n";
        $text .= "#Video #Youtube #Polfr #Politique\n";
        $text .= "https://youtu.be/$identifier";

        $post = Post::create($text);
        $post = $postService->addFacetsFromMentionsAndLinksAndTags($post);

        $image = $this->httpClient
            ->request('GET', "https://i3.ytimg.com/vi/$identifier/hqdefault.jpg")
            ->getContent();

        $post = $postService->addWebsiteCard(
            $post,
            "https://youtu.be/$identifier",
            $channel,
            $title,
            new BlobMediaSource($image, 'image/jpeg'),
        );

        $api->createRecord($post);
    }
}
