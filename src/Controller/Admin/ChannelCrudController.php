<?php

namespace App\Controller\Admin;

use App\Entity\Channel;
use App\Form\ChannelBatchImportType;
use App\Services\ChannelService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Google;
use Symfony\Component\Uid\Uuid;
use function PHPUnit\Framework\isNull;
/**
 * @extends AbstractCrudController<Channel>
 */
#[IsGranted("ROLE_ADMIN")]
class ChannelCrudController extends AbstractCrudController
{
    public function __construct(private KernelInterface $kernel) {}

    public static function getEntityFqcn(): string
    {
        return Channel::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('identifier'),
            TextField::new('title'),
            CollectionField::new('supportLinks')
                ->setEntryType(UrlType::class)
                ->allowAdd()
                ->allowDelete(),
            DateTimeField::new('updatedAt'),
            TextareaField::new('errorStatus'),
            BooleanField::new('isActive'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $batchAdd = Action::new("batchAdd", "Upload CSV", "fa fa-list")
            ->linkToCrudAction("batchAdd")
            ->createAsGlobalAction(); // button at the top of the index page

        return $actions->add(Crud::PAGE_INDEX, $batchAdd);
    }

    #[AdminRoute(path: "/batch-add", name: "batch_add")]
    public function batchAdd(
        Request $request,
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
        ChannelService $service,
        RequestStack $requestStack,
        AdminUrlGenerator $adminUrlGenerator,
    ): Response {
        // $channel = new Channel();
        $form = $this->createForm(ChannelBatchImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $channelsList = explode("\n", $data["channels"]);

            $messages = [];
            $count = 0;

            $repository = $entityManager->getRepository(Channel::class);
            $existingChannels = $repository->findAll();

            $existingIds = [];
            foreach ($existingChannels as $channel) {
                $existingIds += [$channel->getIdentifier() => $channel];
            }

            foreach ($channelsList as $line) {
                $channelInfos = $service->extractIdentifier($line);

                if ($channelInfos == null) {
                    array_push($messages, "Ligne invalide: " . $line);
                    continue;
                }

                $channelIdentifier =
                    "UC" . substr($channelInfos["identifier"], 2);

                $result = $service->addChannel(
                    $channelIdentifier,
                    $channelInfos["name"],
                );

                if ($result) {
                    array_push($messages, $result);
                } else {
                    $count++;
                }

                unset($existingIds[$channelIdentifier]);
            }

            foreach ($existingIds as $existingId) {
                $existingChannel = $entityManager
                    ->getRepository(Channel::class)
                    ->findOneBy(["identifier" => $existingId]);

                if (!isNull($existingChannel)) {
                    $existingChannel->setIsActive(false);
                    $entityManager->persist($existingChannel);
                    $entityManager->flush();
                }
            }

            $report = [
                "total" => sizeof($channelsList),
                "count" => $count,
                "messages" => $messages,
            ];

            $requestStack->getSession()->set("batch_report", $report);

            return $this->redirect(
                $adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl(),
            );
        }

        return $this->render("admin/channel_batch_add.html.twig", [
            // 'channel' => $channel,
            "form" => $form,
        ]);
    }

    public function persistEntity(
        EntityManagerInterface $entityManager,
        $entityInstance,
    ): void {
        $client = new Google\Client();
        $client->setDeveloperKey($_ENV["GOOGLE_API_KEY"]);

        $youtube = new Google\Service\YouTube($client);

        $response = $youtube->channels->listChannels("snippet", [
            "id" => $entityInstance->getIdentifier(),
        ]);

        /** @var Channel $entityInstance */
        parent::persistEntity($entityManager, $entityInstance);

        $items = $response->getItems() ?? [];
        $snippet = $items[0]->getSnippet();

        $thumbUrl = $snippet->getThumbnails()?->getMedium()?->getUrl();
        $httpClient = new HttpClient();
        $image = $httpClient->create()->request("GET", $thumbUrl)->getContent();

        $projectDir = $this->kernel->getProjectDir();
        $imagePath =
            $projectDir .
            "/public/images/channels/" .
            $entityInstance->getIdentifier() .
            ".jpg";

        file_put_contents($imagePath, $image);
    }
}
