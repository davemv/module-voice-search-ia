<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Ai;

use Psr\Log\LoggerInterface;

class MockEmbeddingClient implements EmbeddingProviderInterface
{
    private LoggerInterface $logger;
    public function __construct(LoggerInterface $logger) { $this->logger = $logger; }

    public function getEmbedding(string $text): array {
        $hash = md5(mb_strtolower(trim($text)));
        $vec = [];
        for ($i=0;$i<128;$i++){
            $pair = hexdec(substr($hash, ($i*2)%32, 2));
            $vec[] = ($pair/255.0)-0.5;
        }
        $this->logger->debug('MockEmbeddingClient.getEmbedding: '.substr($text,0,60));
        return $vec;
    }

    public function embed(string $text, ?int $storeId = null): array {
        return $this->getEmbedding($text);
    }
}

