<?php

namespace App\Controller;

use App\Command\ImportKitchenDataCommand;
use App\Service\EmbedderService;
use App\Service\MeiliClientService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    public function __construct(
        private EmbedderService    $embedder,
        private MeiliClientService $meili)
    {
    }

    #[Route('/', name: 'app_homepage')]
    #[Route('/search.{_format}', name: 'search_hybrid', methods: ['GET'])]
    #[Template('app/homepage.html.twig')]
    public function search(
        $_format = '.html',
        #[MapQueryParameter] string $query = 'kitchen utensils made of wood'
    ): JsonResponse|array
    {

        if ($query === '') {
            return new JsonResponse(
                ['error' => 'Missing query parameter "q".'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $vector = $this->embedder->embed($query);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Failed to compute embedding: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $index = $this->meili->getIndex(ImportKitchenDataCommand::INDEX_NAME);
//        $vectorSearch = [
//            "q" => $q,
//            "hybrid" => [
//                "embedder" => "products-openai"
//            ],
//            'vector' => $vector,
//        ];
        $hits = $index->search($query, [
            "hybrid" => [
                "embedder" => "products-openai"
            ],
            "showRankingScore" => true,
            'vector' => $vector,
        ]);
//        dd($hits);
//            dd($hits->getHits());
        try {
//            $hits = $this->meili->vectorSearch($vectorSearch, 10);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'MeiliSearch error: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        $results  = array_map(fn($hit) =>
            array_intersect_key($hit, array_flip([
                'id',
                'name',
                '_rankingScore',
                'description'])),
            $hits->getHits());


        return $_format === 'json'
        ? new JsonResponse([
            'query' => $query,
            'results' => $hits
        ])
            : ['query' => $query,
                'semanticHitCount' => $hits->getSemanticHitCount(),
                'results' => $results, 'hits' => $hits];
    }
}
