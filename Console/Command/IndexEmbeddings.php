<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Console\Command;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use NTT\VoiceSearch\Model\Ai\Embedder;
use NTT\VoiceSearch\Model\Repository\ProductEmbeddingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IndexEmbeddings extends Command
{
    protected static $defaultName = 'ntt:voice:index-embeddings';

    public function __construct(
        private CollectionFactory $collectionFactory,
        private Embedder $embedder,
        private ProductEmbeddingRepository $repo,
        private LoggerInterface $logger
    ) {
        parent::__construct(self::$defaultName);
    }

    protected function configure()
    {
        $this->setDescription('Index product embeddings for VoiceSearch')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Products per batch', 100)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of products', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = (int)$input->getOption('batch-size');
        $limit = $input->getOption('limit') ? (int)$input->getOption('limit') : null;

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name', 'short_description', 'description', 'sku', 'manufacturer']);
        $collection->addAttributeToFilter('status', ['eq' => 1]);
        $collection->addAttributeToFilter('visibility', ['neq' => 1]);
        $collection->setPageSize($limit ?: $batchSize);

        $total = (int)$collection->getSize();
        $pages = (int)ceil($total / ($limit ?: $batchSize));

        $output->writeln("Indexing {$total} products in {$pages} pages");

        for ($page = 1; $page <= $pages; $page++) {
            $collection->setCurPage($page);
            $collection->load();

            foreach ($collection as $product) {
                $vector = $this->embedder->embedProduct($product);
                if (empty($vector)) {
                    $this->logger->warning("No embedding for product {$product->getId()}");
                    continue;
                }
                $this->repo->saveEmbedding((int)$product->getId(), $vector);
            }

            $collection->clear();
        }

        $output->writeln('Done.');
        return Command::SUCCESS;
    }
}
