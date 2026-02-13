<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Nlp;

use Magento\Store\Model\StoreManagerInterface;
use NTT\VoiceSearch\Model\Ai\Embedder;
use NTT\VoiceSearch\Model\Rerank\CosineSimilarity;
use NTT\VoiceSearch\Logger\Logger;

final class AiQueryParser
{
    private const TYPE_MIN_SCORE = 0.55;
    private const COLOR_MIN_SCORE = 0.70;

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

        [$productCandidates, $productAliasToCanonical] = $this->buildCandidates($productMap);
        [$colorCandidates, $colorAliasToCanonical] = $this->buildCandidates($colorMap);

        // 2) Embedding del query
        $qVec = $this->embedder->embedQuery($q);
        $this->log->info('AiQueryParser: embedQuery', [
            'q' => $q,
            'vec_len' => is_array($qVec) ? count($qVec) : 0
        ]);
        if (empty($qVec)) {
            return ['term' => '', 'filters' => [], 'raw' => $q, 'matched' => false];
        }

        // 3) Elegir mejor product_type (incluye variantes, no solo canonical)
        [$bestTypeAlias, $bestTypeScore] = $this->bestMatch($qVec, $productCandidates);
        $bestType = $bestTypeAlias !== null ? ($productAliasToCanonical[$bestTypeAlias] ?? $bestTypeAlias) : null;

        // 4) Elegir mejor color (incluye variantes, no solo canonical)
        [$bestColorAlias, $bestColorScore] = $this->bestMatch($qVec, $colorCandidates);
        $bestColor = $bestColorAlias !== null ? ($colorAliasToCanonical[$bestColorAlias] ?? $bestColorAlias) : null;

        // 5) Umbrales (ajustables por config luego)
        $typeMin  = self::TYPE_MIN_SCORE;
        $colorMin = self::COLOR_MIN_SCORE;

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
            'bestTypeAlias' => $bestTypeAlias,
            'bestType' => $bestType,
            'bestTypeScore' => $bestTypeScore,
            'bestColorAlias' => $bestColorAlias,
            'bestColor' => $bestColor,
            'bestColorScore' => $bestColorScore,
        ]);
        return [
            'term' => $term,
            'filters' => $filters,
            'raw' => $q,
            'matched' => $matched,
            'debug' => [
                'bestTypeAlias' => $bestTypeAlias,
                'bestType' => $bestType, 'bestTypeScore' => $bestTypeScore,
                'bestColorAlias' => $bestColorAlias,
                'bestColor' => $bestColor, 'bestColorScore' => $bestColorScore,
            ]
        ];
    }

    /**
     * @param array<string, array<int, string>> $map
     * @return array{0:array<int, string>, 1:array<string, string>} [candidateTexts, alias => canonical]
     */
    private function buildCandidates(array $map): array
    {
        $candidateTexts = [];
        $aliasToCanonical = [];

        foreach ($map as $canonical => $variants) {
            $canonical = trim((string)$canonical);
            if ($canonical === '') {
                continue;
            }

            if (!isset($aliasToCanonical[$canonical])) {
                $candidateTexts[] = $canonical;
            }
            $aliasToCanonical[$canonical] = $canonical;

            if (!is_array($variants)) {
                continue;
            }

            foreach ($variants as $variant) {
                $variant = trim((string)$variant);
                if ($variant === '') {
                    continue;
                }

                if (!isset($aliasToCanonical[$variant])) {
                    $candidateTexts[] = $variant;
                }
                $aliasToCanonical[$variant] = $canonical;
            }
        }

        return [$candidateTexts, $aliasToCanonical];
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
