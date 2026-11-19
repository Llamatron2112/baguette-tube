<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<User>
 */
#[IsGranted("ROLE_ADMIN")]
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return ["username", "email", "roles", "isVerified", "createdAt"];
    }

    public function configureActions(Actions $actions): Actions
    {
        $batchAdd = Action::new("listEmails", "Emails list", "fa fa-list")
            ->linkToCrudAction("listEmails")
            ->createAsGlobalAction(); // button at the top of the index page

        return $actions->add(Crud::PAGE_INDEX, $batchAdd);
    }

    #[AdminRoute(path: "/list-emails", name: "list_emails")]
    public function listEmails(UserRepository $repository): Response
    {
        $users = $repository->findBy(["isVerified" => "true"]);

        return $this->render("/admin/emails_list.html.twig", [
            "users" => $users,
        ]);
    }
}
