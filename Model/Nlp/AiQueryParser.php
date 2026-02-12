<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Nlp;

use Magento\Store\Model\StoreManagerInterface;
use NTT\VoiceSearch\Model\Ai\Embedder;
use NTT\VoiceSearch\Model\Rerank\CosineSimilarity;
use NTT\VoiceSearch\Logger\Logger;

final class AiQueryParser
{
    public function __construct(
        private StoreManagerInterface $stores,
        private Config $config,
        private AttributeResolver $attrResolver,
        private Embedder $embedder,
        private CosineSimilarity $cosine,
        private Logger $log

    ) {}

    /**
     * @return array{term:string, filters:array, raw:string, matched:bool, debug?:array}
     */
    public function parse(string $q): array
    {
        $storeId = (int)$this->stores->getStore()->getId();

        // 1) Candidatos desde tu JSON
        $productMap = $this->config->getProductTypeMap($storeId); // canonical => variants
        $colorCode  = $this->config->getColorAttributeCode($storeId) ?: 'color';
        $colorMap   = $this->config->getAttributeValueMap($storeId, $colorCode); // canonical label => variants

        $productCanonicals = array_keys($productMap);
        $colorCanonicals   = array_keys($colorMap);

        // 2) Embedding del query
        $qVec = $this->embedder->embedQuery($q);
        $this->log->info('AiQueryParser: embedQuery', [
            'q' => $q,
            'vec_len' => is_array($qVec) ? count($qVec) : 0
        ]);
        if (empty($qVec)) {
            return ['term' => '', 'filters' => [], 'raw' => $q, 'matched' => false];
        }

        // 3) Elegir mejor product_type
        [$bestType, $bestTypeScore] = $this->bestMatch($qVec, $productCanonicals);

        // 4) Elegir mejor color
        [$bestColor, $bestColorScore] = $this->bestMatch($qVec, $colorCanonicals);

        // 5) Umbrales (ajustables por config luego)
        $typeMin  = 0.70;
        $colorMin = 0.70;

        $term = '';
        $filters = [];

        if ($bestType !== null && $bestTypeScore >= $typeMin) {
            $term = (string)$bestType;
        }

        if ($bestColor !== null && $bestColorScore >= $colorMin) {
            $optId = $this->attrResolver->labelToOptionId($colorCode, (string)$bestColor);
            if ($optId) {
                $filters[$colorCode] = $optId;
            }
        }

        $matched = ($term !== '' || !empty($filters));
        $this->log->info('AiQueryParser: best', [
            'bestType' => $bestType,
            'bestTypeScore' => $bestTypeScore,
            'bestColor' => $bestColor,
            'bestColorScore' => $bestColorScore,
        ]);
        return [
            'term' => $term,
            'filters' => $filters,
            'raw' => $q,
            'matched' => $matched,
            'debug' => [
                'bestType' => $bestType, 'bestTypeScore' => $bestTypeScore,
                'bestColor' => $bestColor, 'bestColorScore' => $bestColorScore,
            ]
        ];
    }

    /**
     * @param float[] $qVec
     * @param string[] $canonicals
     * @return array{0:?string,1:float}
     */
    private function bestMatch(array $qVec, array $canonicals): array
    {
        $best = null;
        $bestScore = -INF;

        foreach ($canonicals as $c) {
            $cVec = $this->embedder->embedQuery((string)$c); // cacheado por Embedder
            if (empty($cVec)) continue;

            $score = $this->cosine->score($qVec, $cVec);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = (string)$c;
            }
        }

        return [$best, (float)$bestScore];
    }
}
