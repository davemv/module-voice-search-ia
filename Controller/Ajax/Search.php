<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use NTT\VoiceSearch\Service\NlpRouter;

class Search extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private NlpRouter $router,
        private UrlInterface $urlBuilder
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $query = trim((string)$this->getRequest()->getParam('voice_query', ''));

        if ($query === '') {
            return $result->setData(['success' => false, 'message' => 'No input']);
        }

        $route = $this->router->route($query);

        if (($route['type'] ?? '') === 'redirect') {
            return $result->setData([
                'success' => true,
                'action' => 'redirect',
                'url' => (string)($route['url'] ?? ''),
                'route' => $route,
            ]);
        }

        if (($route['type'] ?? '') === 'search') {
            $term = trim((string)($route['term'] ?? $query));
            $filters = (array)($route['filters'] ?? []);
            $params = ['q' => $term] + $filters;

            $redirectUrl = $this->urlBuilder->getUrl('catalogsearch/result/index', [
                '_query' => $params
            ]);

            return $result->setData([
                'success' => true,
                'action' => 'search',
                'raw' => $query,
                'term' => $term,
                'filters' => $filters,
                'redirectUrl' => $redirectUrl,
                'route' => $route,
            ]);
        }

        // fallback: no-op
        $redirectUrl = $this->urlBuilder->getUrl('catalogsearch/result/index', [
            '_query' => ['q' => $query]
        ]);

        return $result->setData([
            'success' => true,
            'action' => 'search',
            'raw' => $query,
            'term' => $query,
            'filters' => [],
            'redirectUrl' => $redirectUrl,
            'route' => $route,
        ]);
    }
}
