<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Api;

interface SynonymRepositoryInterface
{
    /** Return canonical for a given term (or the term itself if none). */
    public function resolveCanonical(
        int $storeId,
        string $groupCode,             // 'product_type' | 'attribute'
        ?string $attributeCode,        // e.g. 'color' when group=attribute
        string $term
    ): string;

    /** Return all variants for a canonical value (merged JSON+DB). */
    public function getVariants(
        int $storeId,
        string $groupCode,
        ?string $attributeCode,
        string $canonical
    ): array;
}
