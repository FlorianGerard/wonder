<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SecurityController extends AbstractController
{

    public function __construct(
        private FormLoginAuthenticator $authenticator
    ) {}

    #[Route('/signup', name: 'signup')]
    public function signup(UserAuthenticatorInterface $userAuthenticator, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response
    {
        $user = new User();
        $userForm = $this->createForm(UserType::class, $user);
        $userForm->handleRequest($request);
        if ($userForm->isSubmitted() && $userForm->isValid()) {
            $hash = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hash);
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Bienvenue sur Wonder !');
            $email = (new TemplatedEmail())
                ->to($user->getEmail())
                ->subject('Bienvenue sur Wonder !')
                ->htmlTemplate('@email_templates/welcome.html.twig')
                ->context([
                    'username' => $user->getFirstname() . ' ' . $user->getLastname(),
                ]);
            $mailer->send($email);
            return $userAuthenticator->authenticateUser($user, $this->authenticator, $request);
        }
        return $this->render('security/signup.html.twig', ['form' => $userForm->createView()]);
    }

    #[Route('/reset-password/{token}', name: 'reset-password')]
    public function resetPass(string $token): Response
    {
        return $this->json([]);
    }


    #[Route("/login", name: "login")]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route("/logout", name: "logout")]
    public function logout(): void {}

    #[Route("/requestpassword", name: "requestpassword")]
    public function requestPassword(Request $request, UserRepository $userRepository, EntityManagerInterface $em, MailerInterface $mailer, ResetPasswordRepository $resetPasswordRepository): Response
    {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir votre adresse e-mail.',
                    ),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user) {
                $oldResetPassword = $resetPasswordRepository->findOneBy(['user' => $user]); // Remove expired tokens before generating a new one
                if ($oldResetPassword) {
                    $em->remove($oldResetPassword);
                    $em->flush();
                }
                // Generate a password reset token and send an email
                $token = bin2hex(random_bytes(20));
                $resetPassword = new ResetPassword();
                $resetPassword->setUser($user);
                $resetPassword->setToken($token);
                $resetPassword->setExpiredAt(new \DateTimeImmutable('+2 hours'));

                $em->persist($resetPassword);
                $em->flush();

                $email = (new TemplatedEmail())
                    ->to($email)
                    ->subject('Réinitialisation de votre mot de passe')
                    ->htmlTemplate('@email_templates/reset_password.html.twig')
                    ->context([
                        'username' => $user->getFirstname() . ' ' . $user->getLastname(),
                        'token' => $token,
                    ]);
                $mailer->send($email);
            }
            $this->addFlash('success', 'Si un compte avec cet e-mail existe, un lien de réinitialisation a été envoyé.');
        }

        return $this->render('security/request_password.html.twig', ['form' => $form->createView()]);
    }

    #[Route("/resetpassword/{token}", name: "reset_password")]
    public function resetPassword(string $token, Request $request, EntityManagerInterface $em): Response
    {
        $resetPassword = $em->getRepository(ResetPassword::class)->findOneBy(['token' => $token]);
        if (!$resetPassword || $resetPassword->getExpiredAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('requestpassword');
        }

        $form = $this->createFormBuilder()
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir un nouveau mot de passe.',
                    ),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            $user = $resetPassword->getUser();
            $user->setPassword(password_hash($password, PASSWORD_BCRYPT));
            $em->remove($resetPassword);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
            return $this->redirectToRoute('login');
        }

        return $this->render('security/reset_password.html.twig', ['form' => $form->createView()]);
    }
}
