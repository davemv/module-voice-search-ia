<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class Config extends Template
{
    private const XML_PATH_SYNONYMS = 'ntt_voice/nlp/synonyms_json';

    public function __construct(
        Template\Context $context,
        private ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Devuelve el array de términos recomendados definido en el JSON
     * del campo ntt_voice/nlp/synonyms_json.
     */
    public function getRecommendedTerms(): array
    {
        $storeId = (int) $this->_storeManager->getStore()->getId();

        $raw = (string) $this->scopeConfig->getValue(
            self::XML_PATH_SYNONYMS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $rec = $data['recommended'] ?? [];

        // Normalizar a array de strings
        return $rec;
    }
}
