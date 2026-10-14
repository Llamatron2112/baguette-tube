<?php
namespace App\Twig\Components;

use DateTimeImmutable;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use function PHPUnit\Framework\isNull;

#[AsLiveComponent]
class LikeButton
{
    use DefaultActionTrait;

    #[LiveProp]
    public Video $video;

    #[LiveProp]
    public int $count;

    #[LiveProp]
    public string $likers;

    public function __construct(private Security $security) {}

    #[ExposeInTemplate]
    public function likeClass(): ?string
    {
        $user = $this->security->getUser();

        $likes = $this->video->getLikes();

        $this->likers = "";
        foreach ( $likes as $like) {
            $this->likers = $this->likers . $like->getUsername() . "\n";
        }

        $this->count = $likes->count();

        if ($user && $this->video->isLikedBy($user)) {
            return "me";
        }

        if (!$this->video->getLikes()->isEmpty()) {
            return "other";
        }

        return null;
    }

    #[LiveAction]
    public function toggle(EntityManagerInterface $em): void
    {
        $user = $this->security->getUser();

        if ($user && in_array('ROLE_BOUL', $user->getRoles())) {
            if ($this->video->isLikedBy($user)) {
                $this->video->removeLike($user);
            } else {
                $this->video->addLike($user);
                if ( $this->video->getDate() == $this->video->getBumpDate()) {
                    $this->video->setBumpDate(new DateTimeImmutable);
                }
            }

            $em->flush();
        }
    }
}
