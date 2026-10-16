<?php

namespace App\Services;

use App\Entity\Video;
use Symfony\Component\HttpClient\HttpClient;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\HttpComponentsManager;
use potibm\Bluesky\Media\BlobMediaSource;

class BlueskyService
{
    public function publish(Video $video, string $username): void
    {
        $api = new BlueskyApi(
            $_ENV["BLUESKY_HANDLE"],
            $_ENV["BLUESKY_APP_PASSWORD"],
            new HttpComponentsManager(),
            "https://bsky.social/",
        );

        $postService = new \potibm\Bluesky\BlueskyPostService($api);

        $identifier = $video->getIdentifier();
        $channel = $video->getChannel()->getName();
        $title = $video->getTitle();

        $text = "@$username vous recommande\n";
        $text .= "$channel\n";
        $text .= "$title\n";
        $text .= "#Video #Youtube #Polfr #Politique\n";
        $text .= "https://youtu.be/$identifier";

        $post = \potibm\Bluesky\Feed\Post::create($text);
        $post = $postService->addFacetsFromMentionsAndLinksAndTags($post);

        $http = new HttpClient();
        $image = $http
            ->create()
            ->request(
                "GET",
                "https://i3.ytimg.com/vi/$identifier/hqdefault.jpg",
            )->getContent();

        $media = new BlobMediaSource($image, 'image/jpeg');

        $post = $postService->addFacetsFromMentionsAndLinksAndTags($post);
        $post = $postService->addWebsiteCard(
            $post,
            "https://youtu.be/$identifier",
            $channel,
            $title,
            $media,
        );

        $response = $api->createRecord($post);
    }
}
