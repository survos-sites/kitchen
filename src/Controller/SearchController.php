<?php

namespace App\Controller;

use App\Command\ImportKitchenDataCommand;
use App\Command\ImportProductCommand;
use App\Form\SearchFilterType;
use App\Service\EmbedderService;
use App\Service\MeiliClientService;
use Meilisearch\Endpoints\Indexes;
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
        private MeiliClientService $meili)
    {
    }

    #[Route('/search/{indexName}', name: 'search_hybrid',
        methods: ['GET','POST'])]
    #[Template('app/search.html.twig')]
    public function search(
        string                      $indexName,
        Request                     $request,
        #[MapQueryParameter] string $query = 'face cosmetics',
                                    $_format = '.html',
    ): JsonResponse|array
    {
        // need a config!
        $embedder = match($indexName) {
            ImportProductCommand::INDEX_NAME => ImportProductCommand::EMBEDDER,
        };
        $index = $this->meili->getIndex($indexName);
        $defaults = [
            'q' => $query,
            'threshold' => 20,
            'semanticRatio' => 60,
        ];

        // Create the form but don’t handleRequest() here,
        // since we’ll extract values manually in AJAX
        $form = $this->createForm(SearchFilterType::class, $defaults, [
            'action' => $this->generateUrl('search_hybrid', [
                'indexName' => $indexName,
            ]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $query = $form->getData();
            $results = $this->getResults(
                $index,
                $data['q'],
                $embedder,
                $data['threshold'],
                $data['semanticRatio']
            );
        } else {
            $results = null;
        }


//        if ($query === '') {
//            return new JsonResponse(
//                ['error' => 'Missing query parameter "q".'],
//                Response::HTTP_BAD_REQUEST
//            );
//        }
        $params =  [
            'form' => $form->createView(),
            'results' => $results,
            'query' => $query,
            'data' => $data??$defaults, // the query data
        ];
        return $params;
    }

    private function getResults(
        Indexes $index,
        string  $query,
        string  $embedder,
        int $threshold = 20,
        int $semanticRatio = 60,
    )
    {
        $results = $index->search($query, $settings = [
            "hybrid" => [
                "embedder" => $embedder,
                "semanticRatio" => $semanticRatio / 100,
            ],
            "rankingScoreThreshold" => $threshold / 100,
            "showRankingScore" => true,
//            'vector' => $vector,
        ]);
//        dump($settings);

        return $results;
    }

    #[Route('/search/ajax.{_format}', name: 'search_ajax', methods: ['GET'])]
    public function searchAjax(
        Request                  $request,
                                 $_format = '.html',
        #[MapQueryString] string $query = 'bright cosmetics'
    ): JsonResponse|array
    {

        $index = $this->meili->getIndex(ImportKitchenDataCommand::INDEX_NAME);
//        $vectorSearch = [
//            "q" => $q,
//            "hybrid" => [
//                "embedder" => "products-openai"
//            ],
//            'vector' => $vector,
//        ];
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
        $results = array_map(fn($hit) => array_intersect_key($hit, array_flip([
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
