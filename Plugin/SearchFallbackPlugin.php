<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use NTT\VoiceSearch\Model\Rerank\SemanticReranker;
use NTT\VoiceSearch\Model\Repository\ProductEmbeddingRepository;
use Psr\Log\LoggerInterface;

class SearchFallbackPlugin
{
    public function __construct(
        private RequestInterface $request,
        private StoreManagerInterface $storeManager,
        private SemanticReranker $reranker,
        private ProductEmbeddingRepository $embeddingRepository,
        private LoggerInterface $logger
    ) {}

    public function afterLoad(Collection $collection): Collection
    {
        if ($this->request->getFullActionName() !== 'catalogsearch_result_index') {
            return $collection;
        }

        if ($collection->getFlag('ntt_semantic_fallback_applied')) {
            return $collection;
        }

        $q = trim((string)$this->request->getParam('q', ''));
        if ($q === '') {
            return $collection;
        }

        // Si hay resultados del motor, no fallback
        $existingIds = $collection->getAllIds();
        if (!empty($existingIds)) {
            return $collection;
        }

        $this->logger->info('Semantic fallback: start', ['q' => $q]);

        $collection->setFlag('ntt_semantic_fallback_applied', true);
        $this->request->setParam('_ntt_semantic_fallback', 1);

        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();

        $candidateIds = $this->embeddingRepository->getProductIdsForWebsite($websiteId, 1200);
        if (!$candidateIds) {
            $this->logger->warning('Semantic fallback: no candidates', ['websiteId' => $websiteId]);
            return $collection;
        }

        $scores = $this->reranker->rerank($q, $candidateIds);
        if (!$scores) {
            $this->logger->warning('Semantic fallback: empty scores');
            return $collection;
        }

        $orderedIds = array_slice(array_keys($scores), 0, 50);
        if (!$orderedIds) {
            return $collection;
        }

        // ===== CRÍTICO: limpiar el SELECT heredado (el AND (NULL) viene de acá) =====
        $select = $collection->getSelect();

        // resetear completamente WHERE/ORDER/LIMIT para sacar basura del engine
        $select->reset(\Zend_Db_Select::WHERE);
        $select->reset(\Zend_Db_Select::ORDER);
        $select->reset(\Zend_Db_Select::LIMIT_COUNT);
        $select->reset(\Zend_Db_Select::LIMIT_OFFSET);

        // (Opcional pero recomendable) reset GROUP/HAVING si existieran
        $select->reset(\Zend_Db_Select::GROUP);
        $select->reset(\Zend_Db_Select::HAVING);

        // Reset de data interna
        $collection->clear();
        $collection->resetData();

        $collection->setPageSize(50);
        $collection->setCurPage(1);

        // asegurar atributos para render
        $collection->addAttributeToSelect(['name', 'price', 'small_image']);

        // aplicar filtro por ids + orden
        $collection->addIdFilter($orderedIds);
        $collection->getSelect()->order(
            new \Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', array_map('intval', $orderedIds)) . ')')
        );

        // Forzar carga y log
        $collection->load();

        $this->logger->info('Semantic fallback: applied', [
            'q' => $q,
            'top10' => array_slice($orderedIds, 0, 10),
            'loaded_count' => count($collection->getItems()),
            'sql' => (string)$collection->getSelect(),
        ]);

        return $collection;
    }
}
