<?php
declare(strict_types=1);
namespace NTT\VoiceSearch\Model\Rerank;

use NTT\VoiceSearch\Model\Repository\ProductEmbeddingRepository;
use Psr\Log\LoggerInterface;

class SimpleReranker
{
    private ProductEmbeddingRepository $repo;
    private LoggerInterface $logger;

    public function __construct(ProductEmbeddingRepository $repo, LoggerInterface $logger)
    {
        $this->repo = $repo;
        $this->logger = $logger;
    }

    /**
     * @param float[] $queryEmbedding
     * @param int[] $candidateProductIds
     * @return array ordered productId => score (desc)
     */
    public function rerank(array $queryEmbedding, array $candidateProductIds): array
    {
        $scores = [];
        $embeddings = $this->repo->getEmbeddings($candidateProductIds);
        foreach ($embeddings as $pid => $pEmbed) {
            if (empty($pEmbed) || empty($queryEmbedding)) {
                $scores[$pid] = 0.0;
                continue;
            }
            $scores[$pid] = $this->cosineSimilarity($queryEmbedding, $pEmbed);
        }
        arsort($scores);
        return $scores;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0.0 || $nb == 0.0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
