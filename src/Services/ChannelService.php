<?php

namespace App\Services;

use App\Entity\Channel;
use App\Entity\Video;
use DateTimeImmutable;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Google;
use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

class ChannelService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,
    ) {}

    public function addChannel(string $channelId, string $channelName): ?string
    {
        $channel = new Channel();

        $client = new Google\Client();
        $client->setDeveloperKey($_ENV["GOOGLE_API_KEY"]);

        $youtube = new Google\Service\YouTube($client);

        $repository = $this->entityManager->getRepository(Channel::class);

        try {
            $response = $youtube->channels->listChannels("snippet", [
                "id" => $channelId,
            ]);
        } catch (Google\Service\Exception $e) {
            $message =
                "Échec de la requêtes sur l'API YouTube pour " .
                $channelId .
                " : " .
                $e->getMessage();
            return $message;
        }

        $items = $response->getItems() ?? [];

        if ($items === []) {
            $message = "Pas de chaîne correspondante à {$channelId}.";
            return $message;
        }

        if (sizeof($items) > 1) {
            $message =
                "Mauvais identifiant, plusieurs résultats : " . $channelId;
            return $message;
        }

        $existing = $repository->findOneBy(["identifier" => $channelId]);

        if ($existing != null) {
            $message = "Chaîne déjà dans la base : " . $channelId;
            $channel = &$existing;
        }

        $snippet = $items[0]->getSnippet();
        $channel->setIdentifier($channelId);
        $channel->setTitle($snippet->getTitle());
        $channel->setName($channelName);
        // $channel->setUpdatedAt(new DateTimeImmutable());
        $channel->setIsActive(true);

        $thumbUrl = $snippet->getThumbnails()?->getMedium()?->getUrl();
        $httpClient = new HttpClient();
        $image = $httpClient->create()->request("GET", $thumbUrl)->getContent();

        $projectDir = $this->kernel->getProjectDir();
        $imagePath =
            $projectDir . "/public/images/channels/" . $channelId . ".jpg";

        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        $projectDir = $this->kernel->getProjectDir();
        $imagePath =
            $projectDir .
            "/public/images/channels/" .
            $channel->getId() .
            ".jpg";

        file_put_contents($imagePath, $image);

        return null;
    }

    public function extractIdentifier(string $entry): ?array
    {
        // Identifieur seul
        if ($this->validateIdentifier($entry)) {
            return $entry;
        }

        // Ligne du CVS de référence
        $exploded = explode(";", $entry);

        if (sizeof($exploded) == 2) {
            $candidate = trim($exploded[1]);

            $name = trim($exploded[0]);

            if ($this->validateIdentifier($candidate)) {
                return [
                    "identifier" => $candidate,
                    "name" => $name,
                ];
            }
        }

        // TODO: URLs

        return null;
    }

    public function validateIdentifier(string $candidate): bool
    {
        $match = preg_match('/^U[UC][a-zA-Z0-9\-_]{22}$/', $candidate);

        if ($match == 1) {
            return true;
        }

        return false;
    }

    public function refreshFeed(Channel $channel): ?string
    {
        $feedIdentifier = "UU" . substr($channel->getIdentifier(), 2);

        $feedUrl =
            "https://www.youtube.com/feeds/videos.xml?playlist_id=" .
            $feedIdentifier;

        $httpClient = new HttpClient();

        try {
            $feedXml = $httpClient
                ->create()
                ->request("GET", $feedUrl)
                ->getContent();
        } catch (Exception $error) {
            $channel->setErrorStatus($error->getMessage());
            $channel->setUpdatedAt(new DateTimeImmutable());

            $this->entityManager->flush();

            return $error->getMessage();
        }

        $feedData = simplexml_load_string($feedXml);

        $repository = $this->entityManager->getRepository(Video::class);

        foreach ($feedData->entry as $videoData) {
            $videoId = explode(":", $videoData->id)[2];

            if ($repository->findBy(["identifier" => $videoId])) {
                continue;
            }

            $video = new Video();
            $video->setChannel($channel);
            $video->setIdentifier($videoId);
            $video->setTitle($videoData->title);
            $video->setChannel($channel);

            $ytDateFormat = "Y-m-d\TH:i:sP";
            $date = DateTimeImmutable::createFromFormat(
                $ytDateFormat,
                $videoData->published,
            );
            $video->setDate($date);
            $video->setBumpDate($date);

            $this->entityManager->persist($video);
        }

        $channel->setErrorStatus(null);
        $channel->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return null;
    }
}
