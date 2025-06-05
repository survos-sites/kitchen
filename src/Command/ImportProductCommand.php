<?php
namespace App\Command;

use App\Service\EmbedderService;
use App\Service\MeiliClientService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:kitchen-data',
    description: 'Import sample kitchen items into Meilisearch with embeddings'
)]
final class ImportProductCommand
{
    const INDEX_NAME = 'kitchen';

    public function __construct(
        private EmbedderService $embedder,
        private MeiliClientService $meili)
    {
    }

    public function __invoke(
        SymfonyStyle $io
    ): int
    {
        $io->title('Importing sample kitchen data into Meilisearch');

        $sampleItems = [
            ['id' => 1, 'name' => 'Chef Knife',        'description' => 'A high-quality stainless steel chef knife with 8-inch blade.'],
            ['id' => 2, 'name' => 'Cutting Board',     'description' => 'Bamboo cutting board, 15×12 inches, reversible design.'],
            ['id' => 3, 'name' => 'Mixing Bowls Set',  'description' => 'Set of 3 stainless steel mixing bowls (1 qt, 2 qt, 5 qt).'],
            ['id' => 4, 'name' => 'Silicone Spatula',  'description' => 'Heat-resistant silicone spatula, comfortable grip.'],
            ['id' => 5, 'name' => 'Measuring Cups',    'description' => 'Plastic measuring cups set (1/4, 1/3, 1/2, 1, 2 cups).'],
            ['id' => 6, 'name' => 'Blender',           'description' => 'High-speed countertop blender for smoothies and soups.'],
            ['id' => 7, 'name' => 'Cast Iron Skillet', 'description' => '10-inch pre-seasoned cast iron skillet, even heat distribution.'],
            ['id' => 8, 'name' => 'Whisk',             'description' => 'Stainless steel balloon whisk for beating eggs and sauces.'],
            ['id' => 9, 'name' => 'Kitchen Shears',    'description' => 'Heavy-duty kitchen shears with bottle opener feature.'],
            ['id' => 10,'name' => 'Vegetable Peeler',  'description' => 'Stainless steel swivel peeler with ergonomic handle.'],
        ];

        $io->text('Computing embeddings for each item…');
        $docs = [];
        foreach ($sampleItems as $item) {
            $textToEmbed = $item['name'] . '. ' . $item['description'];
            $embedding = $this->embedder->embed($textToEmbed);
            $docs[] = [
                'id'          => $item['id'],
                'name'        => $item['name'],
                'description' => $item['description'],
                '_vector'     => $embedding,
            ];
            $io->text(sprintf('  • Item #%d (%s) embedded.', $item['id'], $item['name']));
        }

        $io->text('Indexing documents into Meilisearch…');
        $this->meili->indexDocuments(self::INDEX_NAME, $docs);

        $io->success('All kitchen items have been imported into Meili index "".' . self::INDEX_NAME);
        return Command::SUCCESS;
    }
}
