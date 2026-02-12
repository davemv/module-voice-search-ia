<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;

class SafeFacetsOnSemanticFallback
{
    public function __construct(private RequestInterface $request) {}

    public function aroundGetFacetedData(Collection $subject, \Closure $proceed, $field)
    {
        if ((int)$this->request->getParam('_ntt_semantic_fallback') === 1) {
            return [];
        }
        return $proceed($field);
    }
}
