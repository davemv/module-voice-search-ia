<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Ai;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class Embedder
{
    public function __construct(
        private EmbeddingProviderInterface $provider,
        private CacheInterface $cache,
        private Json $json,
        private StoreManagerInterface $stores
    ) {}

    private function cacheKey(string $k): string { return 'NTT_VOICE_EMB_' . md5($k); }

    /** @return float[] */
    public function embedQuery(string $q): array
    {
        $storeId = (int)$this->stores->getStore()->getId();
        $key = $this->cacheKey('q:' . $storeId . ':' . mb_strtolower(trim($q)));
        if ($hit = $this->cache->load($key)) {
            return (array)$this->json->unserialize($hit);
        }
        $vec = $this->provider->embed($q, $storeId);
        if ($vec) $this->cache->save($this->json->serialize($vec), $key, ['NTT_VOICE_AI'], $this->ttl());
        return $vec;
    }

    /** @return float[] */
    public function embedProduct(ProductInterface $p): array
    {
        $storeId = (int)$this->stores->getStore()->getId();
        $key = $this->cacheKey('p:' . $storeId . ':' . (int)$p->getId());
        if ($hit = $this->cache->load($key)) {
            return (array)$this->json->unserialize($hit);
        }
        $text = $this->productText($p);
        $vec  = $this->provider->embed($text, $storeId);
        if ($vec) $this->cache->save($this->json->serialize($vec), $key, ['NTT_VOICE_AI'], $this->ttl());
        return $vec;
    }

    private function productText(ProductInterface $p): string
    {
        $parts = [
            (string)$p->getName(),
            (string)$p->getData('short_description'),
            (string)$p->getData('description'),
            (string)$p->getSku(),
        ];
        // agrega atributos útiles si querés, ej. brand, color, category_names (si los tenés precalculados)
        return trim(implode("\n", array_filter(array_map('strip_tags', $parts))));
    }

    private function ttl(): int
    {
        $minutes = 60 * 24; // fallback 1 día
        try {
            $minutes = (int)\Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Framework\App\Config\ScopeConfigInterface::class)
                ->getValue('ntt_voice/ai/ttl_minutes');
        } catch (\Throwable) {}
        return max(60, $minutes * 60);
    }
}
