<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Ai;

use NTT\VoiceSearch\Model\Ai\Embedder;
use NTT\VoiceSearch\Model\Nlp\Config;
use NTT\VoiceSearch\Model\Rerank\CosineSimilarity;
use Magento\Store\Model\StoreManagerInterface;

class SemanticCanonicalResolver
{
    public function __construct(
        private Embedder $embedder,
        private Config $nlpConfig,
        private CosineSimilarity $cosine,
        private StoreManagerInterface $stores
    ) {}

    /**
     * Devuelve el canonical del product_type más cercano (p.ej. "jacket") o null.
     */
    public function resolveProductType(string $q, float $minScore = 0.80): ?array
    {
        $storeId = (int)$this->stores->getStore()->getId();
        $map = $this->nlpConfig->getProductTypeMap($storeId); // canonical => variants

        if (!$map) return null;

        $qVec = $this->embedder->embedQuery($q);
        if (!$qVec) return null;

        $bestCanon = null;
        $bestScore = -1.0;

        foreach (array_keys($map) as $canonical) {
            $canonVec = $this->embedder->embedQuery((string)$canonical);
            if (!$canonVec) continue;

            $s = $this->cosine->score($qVec, $canonVec);
            if ($s > $bestScore) {
                $bestScore = $s;
                $bestCanon = (string)$canonical;
            }
        }

        if ($bestCanon !== null && $bestScore >= $minScore) {
            return ['canonical' => $bestCanon, 'score' => $bestScore];
        }
        return null;
    }
}
