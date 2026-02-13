<?php
declare(strict_types=1);
namespace NTT\VoiceSearch\Model\Ai;

use Psr\Log\LoggerInterface;
use Magento\Framework\HTTP\Client\Curl;

class EmbeddingClient
{
    private Curl $curl;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $model;

    public function __construct(
        Curl $curl,
        LoggerInterface $logger,
        ?string $apiKey = null,
        string $model = 'text-embedding-3-small'
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->apiKey = $apiKey ?? (getenv('VOICESEARCH_OPENAI_KEY') ?: '');
        $this->model = $model;
    }

    /**
     * @return float[]|null
     */
    public function getEmbedding(string $text): ?array
    {
        if (empty($this->apiKey)) {
            $this->logger->warning('EmbeddingClient: no API key provided (VOICESEARCH_OPENAI_KEY).');
            return null;
        }

        try {
            $url = 'https://api.openai.com/v1/embeddings';
            $payload = json_encode([
                'model' => $this->model,
                'input' => $text
            ]);

            $this->curl->addHeader('Authorization', 'Bearer ' . $this->apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post($url, $payload);

            $status = $this->curl->getStatus();
            if ($status !== 200) {
                $this->logger->error("EmbeddingClient: status {$status} body: " . $this->curl->getBody());
                return null;
            }

            $body = json_decode($this->curl->getBody(), true);
            return $body['data'][0]['embedding'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error('EmbeddingClient error: ' . $e->getMessage());
            return null;
        }
    }
}
