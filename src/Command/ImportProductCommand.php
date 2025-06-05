<?php
namespace App\Command;

use App\Service\EmbedderService;
use App\Service\MeiliClientService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'import:products',
    description: 'Import dummy products into Meilisearch with embeddings'
)]
final class ImportProductCommand
{
    const INDEX_NAME = 'dummy_products';
    const EMBEDDER = 'openai_dummy_products';

    public function __construct(
        #[Autowire('%env(OPENAI_API_KEY)%')] private string $openAiApiKey,
        private CacheInterface $cache,
        private MeiliClientService $meili)
    {
    }

    public function __invoke(
        SymfonyStyle $io,

        #[Option("maximum number of products to import")] int $limit=50,
        #[Option("Initialize the index")] bool $init=false,
        #[Option("index the docs", name: 'index')] bool $createIndex=false
    ): int
    {
        $client = $this->meili->getClient();
        if ($init) {
            $io->text('Initializing the index');
            try {
                $index = $client->getIndex(self::INDEX_NAME);
            } catch (\Throwable $e) {
                if ($e->getCode() === 404) {
                    $createIndexTask = $client->createIndex(self::INDEX_NAME);
                    $client->waitForTask($createIndexTask);
                }
            }
            $documentTemplate = '
                Product {{ doc.sku }} is {{ doc.title }}.
                It is tagged with
                {% for tag in doc.tags %}
  {{ user }}
{% endfor %}

                and is described as {{ doc.description | truncatewords: 20 }}
                ';
            $embedder = [
                self::EMBEDDER => [
                    'source' => 'openAi',
                    'model' => 'text-embedding-3-small',
                    'apiKey' => $this->openAiApiKey,
                    'documentTemplate' => $documentTemplate,
                ]
            ];
            $task = $index->updateEmbedders(
                $embedder,
            );
            $results = $client->waitForTask($task['taskUid']);
        }


        if ($createIndex) {
            $batch = 50;
            for ($start = 0; $start < 200; $start += $batch) {
                $url = "https://dummyjson.com/products?limit=$batch&skip=$start";
                $io->title("Importing $url");
                $products = $this->cache->get(md5($url),
                    fn() => json_decode(file_get_contents($url)));


                $docs = [];
                foreach ($products->products as $idx => $product) {
                    $io->text('Computing embeddings for ' . $product->title);
                    $docs[] = $product;
//                    if ($limit && ($idx >= $limit)) {
//                        break;
//                    }
                }

                $io->text('Indexing documents into Meilisearch…' . count($docs));
                $this->meili->indexDocuments(self::INDEX_NAME, $docs);
            }

            $io->success(
                sprintf('%d dummy items have been imported into Meili index "".' . self::INDEX_NAME, count($docs))
            );

        }

        // @todo: wait
//
//        $hits = $index->search('red cosmetics', [
//            'retrieveVectors' => true,
//            "rankingScoreThreshold" => 0.5,
//            'showRankingScoreDetails' => true,
//            'hybrid' => [
//                'embedder' => self::EMBEDDER,
//            ]
//        ]);
//        foreach ($hits->getHits() as $hit) {
//            $io->writeln(json_encode($hit));
//        }

        return Command::SUCCESS;
    }
}
