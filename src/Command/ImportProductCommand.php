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
use Symfony\Component\OptionsResolver\OptionsResolver;
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
        #[Option("create the index")] bool $init=false,
        #[Option("import and index the data", name: 'import')] bool $import=false
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
            $documentTemplate = 'Product {{ doc.sku }} is {{ doc.title }}.
Category is {{ doc.category }}.  Product tags are {% for tag in doc.tags %} {{ tag }} {% endfor %}';
//
//                and is described as {{ doc.description | truncatewords: 20 }}

            $embedder = [
                self::EMBEDDER => [
                    'source' => 'openAi',
                    'model' => 'text-embedding-3-small',
                    'apiKey' => $this->openAiApiKey,
                    'documentTemplate' => $documentTemplate,
                ]
            ];
            dd($embedder);
            $task = $index->updateEmbedders(
                $embedder,
            );
            $results = $client->waitForTask($task['taskUid']);
            if ($results['status'] <> 'succeeded') {
                dd($results);
            }
        }
        $index = $client->getIndex(self::INDEX_NAME);


        if ($import) {
            $batch = 50;
            for ($start = 0; $start < 200; $start += $batch) {
                $url = "https://dummyjson.com/products?limit=$batch&skip=$start";
                $io->title("Importing $url");
                $products = $this->cache->get(md5($url),
                    fn() => json_decode(file_get_contents($url)));

                $docs = [];
                foreach ($products->products as $idx => $product) {
                    $io->text('Computing embeddings for ' . $product->title);
                    $product = new OptionsResolver()
                        ->setDefined(array_keys((array) $product))
                        ->setDefaults([
                            'tags' => [],
                            'category' => null,
                            'title'=> null,
                            'sku'=> null,
                            'description'=> null,
                        ])->resolve((array)$product);
                    $docs[] = $product;
                    $this->meili->indexDocuments(self::INDEX_NAME, $docs);
                    if ($limit && ($start >= $limit)) {
                        break;
                    }
                }

                $io->text('Indexing documents into Meilisearch…' . count($docs));
                $this->meili->indexDocuments(self::INDEX_NAME, $docs);
            }

            $io->success(
                sprintf('%d dummy items have been imported into Meili index "".' . self::INDEX_NAME, count($docs))
            );

        }

        $hits = $index->search('red cosmetics', [
            'retrieveVectors' => true,
            "rankingScoreThreshold" => 0.5,
            'showRankingScoreDetails' => true,
            "attributesToHighlight" => [
                "description",
                "title",
            ],
            'hybrid' => [
                'embedder' => self::EMBEDDER,
            ]
        ]);
        foreach ($hits->getHits() as $hit) {
//            dd($hit, array_keys($hit));
            unset($hit['_vectors']);
//            unset($hit->_vectors);
            $io->writeln(json_encode($hit, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
            break;
        }
        // @todo: wait


        return Command::SUCCESS;
    }
}
