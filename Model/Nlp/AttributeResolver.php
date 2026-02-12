<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Nlp;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AttributeResolver
{
    public function __construct(
        private ProductAttributeRepositoryInterface $productAttrRepo,
        private ScopeConfigInterface $scopeConfig
    ) {}

    public function getColorAttributeCode(int $storeId): string
    {
        $code = (string)$this->scopeConfig->getValue('ntt_voice/nlp/color_attribute', ScopeInterface::SCOPE_STORE, $storeId);
        return $code ?: 'color';
    }

    public function colorLabelToOptionId(int $storeId, string $label): ?int
    {
        $attrCode = $this->getColorAttributeCode($storeId);
        if (!$attrCode) return null;
        return $this->labelToOptionId($attrCode, $label);
    }

    public function labelToOptionId(string $attrCode, string $label): ?int
    {
        try {
            $attr = $this->productAttrRepo->get($attrCode);
        } catch (NoSuchEntityException) {
            return null;
        }
        $needle = mb_strtolower(trim($label));
        foreach ($attr->getOptions() as $opt) {
            $lab = mb_strtolower((string)$opt->getLabel());
            if ($lab === $needle) {
                return (int)$opt->getValue();
            }
        }
        return null;
    }
}
