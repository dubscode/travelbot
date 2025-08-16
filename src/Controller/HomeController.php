<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // If user is authenticated, redirect to chat
        if ($this->getUser()) {
            return $this->redirectToRoute('app_chat');
        }
        
        // If user is not authenticated, redirect to login
        return $this->redirectToRoute('app_login');
    }
}