<?php
declare(strict_types=1);
namespace NTT\VoiceSearch\Model\Ai;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class RouterProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private ScopeConfigInterface $cfg,
        private OpenAiProvider $openai,
        private MockProvider $mock
    ) {}

    public function embed(string $text, int $storeId): array {
        $prov = (string)$this->cfg->getValue('ntt_voice/ai/provider', ScopeInterface::SCOPE_STORE, $storeId);
        return $prov === 'mock' ? $this->mock->embed($text, $storeId)
                                : $this->openai->embed($text, $storeId);
    }
}
