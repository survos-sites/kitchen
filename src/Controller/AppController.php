<?php

namespace App\Controller;

use App\Command\ImportKitchenDataCommand;
use App\Command\ImportProductCommand;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    #[Template('app/homepage.html.twig')]
    public function index(): Response|array
    {
        return $this->redirectToRoute('search_hybrid', [
            'indexName' => ImportProductCommand::INDEX_NAME,
        ]);
        return [
            'db' => [
                ImportProductCommand::INDEX_NAME,
                ImportKitchenDataCommand::INDEX_NAME,
            ]
        ];
    }
}
