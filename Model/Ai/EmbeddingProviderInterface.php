<?php
declare(strict_types=1);
namespace NTT\VoiceSearch\Model\Ai;

interface EmbeddingProviderInterface
{
    /** @return float[] */
    public function embed(string $text, int $storeId): array;
}
