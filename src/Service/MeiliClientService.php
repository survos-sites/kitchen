<?php
namespace App\Service;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MeiliClientService
{
    private Client $client;
//    private string $indexName = 'kitchen';
    private int $vectorSize = 1536;
    private string $vectorDistance = 'Cosine';

    public function __construct(
        private EmbedderService $embedder,
        private string $openAiApiKey,
//        #[Autowire('%(env(MEILISEARCH_HOST)%')]
        private string $meiliHost,
//        #[Autowire('%(env(MEILISEARCH_API_KEY)%')]
        private string $meiliApiKey
    )
    {
        $this->client = $this->getClient(); // make lazy?
    }

    public function getClient(): Client
    {
        if (!isset($this->client)) {
            $this->client = new Client($this->meiliHost, $this->meiliApiKey);
        }
        return $this->client;

    }

    public function getIndex(string $indexCode): Indexes
    {
//        $this->ensureIndexExists($indexCode);
        return $this->getClient()->index($indexCode);
    }

    private function ensureIndexExists(string $indexName,
        ?string $documentTemplate = null,

    ): void
    {
        $index = $this->client->getIndex($indexName);

        $embedder = [
            'products-openai' => [
                'source' => 'openAi',
                "dimensions" => 1536,
                'model' => 'text-embedding-3-small',
                'apiKey' => $this->openAiApiKey,
//                'documentTemplate' => 'An object used in a kitchen named "{{doc.name}}"',
            ]
        ];
//        'vector' => [
//        'size' => $this->vectorSize,
//        'distance' => $this->vectorDistance,
//    ],
        try {
            $index
                ->updateSettings([
                    'embedders' => $embedder,
                ]);
//            dd($index->getSettings(), $embedder);
        } catch (ApiException $e) {
//            dd($e->getMessage(), $e::class, $e->getCode());
            if ($e->getCode() === 404) {
//            if (strpos($e->getMessage(), 'not found') !== false) {
                $task = $this->client->createIndex($this->indexName, [ 'primaryKey' => 'id' ]);
                dd($task);
                $task = $this->client
                    ->getIndex($this->indexName)
                    ->updateSettings([
                        'vector' => [
                            'size' => $this->vectorSize,
                            'distance' => $this->vectorDistance,
                        ],
                    ]);
                dd($task);
                $this->client->waitForTask($task['uid'])
                    ->wait();
            } else {
                throw $e;
            }
        }
    }

    public function indexDocuments(string $indexName, array $docs): void
    {
        $task = $this->client
            ->index($indexName)
            ->addDocuments($docs);
        $results = $this->client->waitForTask($task['taskUid']);
        if ($results['status'] <> 'succeeded') {
            dd($results, $docs);
        }
    }

    public function vectorSearch(string $indexName, array $vector, int $limit = 10): array
    {
        $params = [
            'vector' => $vector,
            'limit' => $limit,
        ];
        $response = $this->client->index($indexName)->search('', $params);
        return $response->getHits();
    }
}
