<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Nlp;

use NTT\VoiceSearch\Api\SynonymRepositoryInterface;

class JsonSynonymRepository implements SynonymRepositoryInterface
{
    public function __construct(
        private Config $config
    ) {}

    public function resolveCanonical(
        int $storeId,
        string $groupCode,
        ?string $attributeCode,
        string $term
    ): string {
        $termNorm = mb_strtolower(trim($term));
        if ($termNorm === '') {
            return $term;
        }

        $map = [];
        if ($groupCode === 'product_type') {
            $map = $this->config->getProductTypeMap($storeId);
        } elseif ($groupCode === 'attribute' && $attributeCode) {
            $map = $this->config->getAttributeValueMap($storeId, $attributeCode);
        }

        if (!$map) {
            return $term;
        }

        foreach ($map as $canonical => $variants) {
            if (!is_array($variants)) {
                continue;
            }
            $normalized = array_map(
                fn($v) => mb_strtolower(trim((string)$v)),
                $variants
            );
            if (in_array($termNorm, $normalized, true)) {
                return (string)$canonical;
            }
        }

        return $term; // sin match ⇒ devolvemos el original
    }

    public function getVariants(
        int $storeId,
        string $groupCode,
        ?string $attributeCode,
        string $canonical
    ): array {
        $map = [];
        if ($groupCode === 'product_type') {
            $map = $this->config->getProductTypeMap($storeId);
        } elseif ($groupCode === 'attribute' && $attributeCode) {
            $map = $this->config->getAttributeValueMap($storeId, $attributeCode);
        }

        $variants = $map[$canonical] ?? [];
        return is_array($variants) ? $variants : [];
    }
}
