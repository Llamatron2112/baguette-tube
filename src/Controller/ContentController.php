<?php

namespace App\Controller;

use App\Repository\ChannelRepository;
use App\Repository\VideoRepository;
use App\Services\ChannelService;
use App\Entity\Channel;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContentController extends AbstractController
{
    #[Route("/channels", name: "app_content_channels", methods: ["GET"])]
    public function channels(ChannelRepository $repository): Response
    {
        return $this->render("content/channels.html.twig");
    }

    #[Route("/feed/{id}", name: "app_content_feed", methods: ["GET"])]
    public function feed(
        Channel $channel,
        VideoRepository $repository,
        ChannelService $service,
        PaginatorInterface $paginator,
        Request $request,
    ): Response {

        if ($channel->isActive()) {
            $service->refreshFeed($channel);
        }

        $qb = $repository
            ->createQueryBuilder("v")
            ->andWhere("v.channel = :channel")
            ->setParameter("channel", $channel->getId(), UuidType::NAME)
            ->orderBy("v.date", "DESC");

        if ($search = $request->query->get("q")) {
            $qb->andWhere("v.title LIKE :q")->setParameter(
                "q",
                "%" . $search . "%",
            );
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt("page", 1),
            12,
        );

        return $this->render("content/channel.html.twig", [
            "pagination" => $pagination,
            "channel" => $channel,
        ]);
    }

    #[Route("/latest", name: "app_content_latest", methods: ["GET"])]
    public function index(
        Request $request,
        VideoRepository $repository,
        PaginatorInterface $paginator,
    ): Response {
        $qb = $repository->createQueryBuilder("v")->orderBy("v.date", "DESC");

        if ($search = $request->query->get("q")) {
            $qb->andWhere("v.title LIKE :q")->setParameter(
                "q",
                "%" . $search . "%",
            );
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt("page", 1),
            12,
        );

        return $this->render("content/videos-list.html.twig", [
            "title" => "Toutes les vidéos",
            "pagination" => $pagination,
        ]);
    }

    #[Route("/", name: "app_content_recommended", methods: ["GET"])]
    public function recommended(
        VideoRepository $repository,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator,
        Request $request,
    ): Response {
        $page = (int) $request->query->get("p");
        $page = $page == null ? 1 : (int) $page;

        $qb = $repository
            ->createQueryBuilder("v")
            ->andWhere("v.likes IS NOT EMPTY")
            ->orderBy("v.bumpDate", "DESC");

        if ($search = $request->query->get("q")) {
            $qb->andWhere("v.title LIKE :q")->setParameter(
                "q",
                "%" . $search . "%",
            );
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt("page", 1),
            12,
        );

        return $this->render("content/videos-list.html.twig", [
            "title" => "Vidéos recommandées",
            "pagination" => $pagination,
        ]);
    }

    #[Route(path: "/video/{id}", name: "app_content_video", methods: ["GET"])]
    public function video(Video $video): Response
    {
        return $this->render("content/video.html.twig", [
            "video" => $video,
        ]);
    }

    // #[Route(path: "/user/{id}", name: "app_content_user", methods: ["GET"])]
    // public function user(User $user): Response
    // {
    //     return $this->render("content/user.html.twig", [
    //         "user" => $user,
    //     ]);
    // }
}
