<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;


class UsersController extends AbstractController
{   
    // #[Route('/users', name: 'app_users', methods: ['GET'])]
    public function index(LoggerInterface $logger): Response|JsonResponse
    {
        $logger->info('UsersController index method called');
        $logger->error('This is an error log from UsersController index method');
        return new Response('Users index');
    }
}