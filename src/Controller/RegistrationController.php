<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use App\Form\ResendVerificationEmailType;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier) {}

    #[Route("/register", name: "app_register")]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get("plainPassword")->getData();

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword($user, $plainPassword),
            );

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->sendVerificationEmail($user);

            return $this->redirectToRoute("app_verify_email_sent");
        }

        return $this->render("registration/register.html.twig", [
            "registrationForm" => $form,
        ]);
    }

    #[Route("/verify/email", name: "app_verify_email")]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        UserRepository $userRepository,
    ): Response {
        $id = $request->query->get("id");

        if (null === $id) {
            return $this->redirectToRoute("app_register");
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute("app_register");
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                "verify_email_error",
                $translator->trans(
                    $exception->getReason(),
                    [],
                    "VerifyEmailBundle",
                ),
            );

            return $this->redirectToRoute("app_register");
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash("success", "Your email address has been verified.");

        return $this->redirectToRoute("app_login");
    }

    #[Route("/verify/email-sent", name: "app_verify_email_sent")]
    public function verifyUserEmailSent(): Response
    {
        return $this->render("registration/email_sent.html.twig");
    }

    private function sendVerificationEmail(User $user): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            "app_verify_email",
            $user,
            (new TemplatedEmail())
                ->from(new Address($_ENV["EMAIL_FROM"], "Baguette Tube"))
                ->to((string) $user->getEmail())
                ->subject("Confirmez votre email")
                ->htmlTemplate("registration/confirmation_email.html.twig"),
        );
    }

    #[Route("/verify/email/resend", name: "app_resend_verification_email", methods: ["GET", "POST"])]
    public function resendVerificationEmail(
        Request $request,
        UserRepository $userRepository,
    ): Response {
        $form = $this->createForm(ResendVerificationEmailType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = trim((string) $form->get("email")->getData());
            $user = $userRepository->findOneBy(["email" => $email]);

            if (null !== $user && !$user->isVerified()) {
                $lastRequest = $user->getVerificationRequestedAt();

                if (null === $lastRequest || $lastRequest <= new \DateTimeImmutable("-5 minutes")) {
                    $this->sendVerificationEmail($user);
                }
            }

            return $this->redirectToRoute("app_verify_email_sent");
        }

        return $this->render("registration/resend_verification_email.html.twig", [
            "resendForm" => $form,
        ]);
    }
}
