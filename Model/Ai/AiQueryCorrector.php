<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Ai;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class AiQueryCorrector
{
    public function __construct(
        private ScopeConfigInterface $cfg,
        private StoreManagerInterface $stores
    ) {}

    public function isEnabled(): bool
    {
        $storeId = (int)$this->stores->getStore()->getId();
        return (bool)$this->cfg->getValue('ntt_voice/ai/enable_query_correct', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function threshold(): float
    {
        $storeId = (int)$this->stores->getStore()->getId();
        $v = (float)$this->cfg->getValue('ntt_voice/ai/query_correct_threshold', ScopeInterface::SCOPE_STORE, $storeId);
        return $v > 0 ? $v : 0.80;
    }
}
