<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Rerank;

use NTT\VoiceSearch\Model\Ai\Embedder;
use NTT\VoiceSearch\Model\Repository\ProductEmbeddingRepository;

class SemanticReranker
{
    public function __construct(
        private Embedder $embedder,
        private ProductEmbeddingRepository $repository,
        private CosineSimilarity $cosine
    ) {}

    /**
     * @param string $query
     * @param int[] $productIds
     * @return array<int, float> productId => score (desc)
     */
    public function rerank(string $query, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $queryVector = $this->embedder->embedQuery($query);
        if (empty($queryVector)) {
            return [];
        }

        $embeddings = $this->repository->getEmbeddings($productIds);

        $scores = [];
        foreach ($embeddings as $productId => $vector) {
            if (empty($vector)) {
                continue;
            }
            $scores[$productId] = $this->cosine->score($queryVector, $vector);
        }

        arsort($scores, SORT_NUMERIC);
        return $scores;
    }
}
