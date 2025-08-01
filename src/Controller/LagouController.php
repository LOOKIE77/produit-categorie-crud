<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LagouController extends AbstractController
{
    #[Route('/lagou', name: 'app_lagou')]
    public function index(): Response
    {
        return $this->render('lagou/index.html.twig', [
            'controller_name' => 'LagouController',
        ]);
    }
}
