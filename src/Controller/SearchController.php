<?php

namespace App\Controller;

use App\Service\EmbedderService;
use App\Service\MeiliClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    public function __construct(
        private EmbedderService    $embedder,
        private MeiliClientService $meili)
    {
    }

    #[Route('/search', name: 'search_kitchen', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        $query = "kitchen utensils made of wood";
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

        $index = $this->meili->getIndex('kitchen');
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
            'vector' => $vector,

        ]);
//            dd($hits->getHits());
        try {
//            $hits = $this->meili->vectorSearch($vectorSearch, 10);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'MeiliSearch error: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        $new_array =

        $hits = array_map(fn($hit) =>
            array_intersect_key($hit, array_flip(['id', 'name', 'description'])),
            $hits->getHits());

        return new JsonResponse([
            'query' => $query,
            'results' => $hits
        ]);
    }
}
