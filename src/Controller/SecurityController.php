<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Discord-only login page (the form is replaced by the Discord button).
        return $this->render('admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'page_title' => '🐕 Dog Help Scheduler',
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('This is intercepted by the logout key on the firewall.');
    }
}
