<?php
declare(strict_types=1);
namespace NTT\VoiceSearch\Model\Ai;

class MockProvider implements EmbeddingProviderInterface
{
    public function embed(string $text, int $storeId): array {
        // vector pseudo-aleatorio pero determinista por texto
        $h = md5(mb_strtolower(trim($text)));
        $vec = [];
        for ($i=0; $i<128; $i++) {
            $chunk = hexdec(substr($h, ($i*2)%32, 2));
            $vec[] = ($chunk/255.0) * 2 - 1; // rango [-1,1]
        }
        return $vec;
    }
}
