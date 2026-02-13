<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Ai;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\Client\Curl;
use NTT\VoiceSearch\Logger\Logger;
class OpenAiProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private ScopeConfigInterface $cfg,
        private Curl $curl,
        private Logger $log
    ) {}

    public function embed(string $text, int $storeId): array
    {
        $key   = (string)$this->cfg->getValue('ntt_voice/ai/openai_api_key', ScopeInterface::SCOPE_STORE, $storeId);
        $model = (string)$this->cfg->getValue('ntt_voice/ai/openai_model', ScopeInterface::SCOPE_STORE, $storeId) ?: 'text-embedding-3-small';

        if ($key === '') { $this->log->warning('AI disabled: empty key'); return []; }

        $payload = json_encode(['model' => $model, 'input' => $text], JSON_UNESCAPED_UNICODE);
        $this->curl->setHeaders(['Authorization' => 'Bearer '.$key, 'Content-Type'=>'application/json']);
        $this->curl->setTimeout(6);
        $this->curl->post('https://api.openai.com/v1/embeddings', $payload);

        $status = (int)$this->curl->getStatus();
        if ($status !== 200) {
            $this->log->error('OpenAI error', [
                'status' => $status,
                'body'   => substr((string)$this->curl->getBody(), 0, 500)
            ]);
            return [];
        }

        $data = json_decode($this->curl->getBody(), true);
        if (empty($data['data'][0]['embedding'])) {
            $this->log->error('OpenAI: invalid payload', ['body' => substr((string)$this->curl->getBody(), 0, 500)]);
            return [];
        }
        return array_map('floatval', $data['data'][0]['embedding']);
    }
}
