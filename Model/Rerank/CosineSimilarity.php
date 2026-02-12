<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Rerank;

class CosineSimilarity
{
    /**
     * @param float[] $a
     * @param float[] $b
     */
    public function score(array $a, array $b): float
    {
        if (count($a) !== count($b) || empty($a)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $normA += $v * $v;
            $normB += $b[$i] * $b[$i];
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
