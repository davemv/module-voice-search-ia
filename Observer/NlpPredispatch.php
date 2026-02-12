<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Observer;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use NTT\VoiceSearch\Service\NlpRouter;
use NTT\VoiceSearch\Logger\Logger as AiLogger;
use NTT\VoiceSearch\Model\Nlp\AiQueryParser;

class NlpPredispatch implements ObserverInterface
{
    public function __construct(
        private HttpRequest $request,
        private HttpResponse $response,
        private RedirectInterface $redirect,
        private NlpRouter $router,
        private AiQueryParser $aiParser,
        private ScopeConfigInterface $scopeConfig,
        private StoreManagerInterface $stores,
        private AiLogger $aiLogger,
        private UrlInterface $urlBuilder,
    ) {}

    public function execute(Observer $observer): void
    {
        $fullAction = (string)$this->request->getFullActionName();
        if ($fullAction !== 'catalogsearch_result_index') {
            return;
        }

        $qOriginal = trim((string)$this->request->getParam('q', ''));
        if ($qOriginal === '') {
            return;
        }

        $storeId = (int)$this->stores->getStore()->getId();

        $this->aiLogger->info('NlpPredispatch: start', [
            'fullAction' => $fullAction,
            'q' => $qOriginal,
        ]);

        // ✅ Modo intención (json/ai/both)
        $mode = (string)$this->scopeConfig->getValue(
            'ntt_voice/ai/intent_mode',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $mode = trim($mode) ?: 'json';

        // 1) Router (JSON + redirects)
        $route = $this->router->route($qOriginal);

        $this->aiLogger->info('NlpPredispatch: routed', [
            'q_original' => $qOriginal,
            'mode' => $mode,
            'route' => $route,
        ]);

        // 1.a) Redirects explícitos (url externa o interna)
        if (($route['type'] ?? '') === 'redirect') {
            $url = (string)($route['url'] ?? '');
            if ($url !== '') {

                $this->aiLogger->info('NlpPredispatch: redirect', [
                    'q' => $qOriginal,
                    'url' => $url
                ]);
                $this->redirect->redirect($this->response, $url);
            }
            return;
        }

        // 2) IA (si corresponde)
        if (in_array($mode, ['ai', 'both'], true)) {
            $ai = $this->aiParser->parse($qOriginal);

            $this->aiLogger->info('NlpPredispatch: ai-parse', [
                'q' => $qOriginal,
                'ai' => $ai
            ]);

            $aiTerm = trim((string)($ai['term'] ?? ''));
            $aiFilters = (array)($ai['filters'] ?? []);

            if (!empty($ai['matched']) && $aiTerm !== '' && strcasecmp($aiTerm, $qOriginal) !== 0) {

                $url = $this->urlBuilder->getUrl('catalogsearch/result/index', [
                    '_query' => (['q' => $aiTerm] + $aiFilters)
                ]);

                $this->aiLogger->info('NlpPredispatch: ai-redirect', [
                    'from' => $qOriginal,
                    'to' => $aiTerm,
                    'filters' => $aiFilters,
                    'url' => $url
                ]);

                $this->redirect->redirect($this->response, $url);
                return;
            }

            // Si mode=ai y no matcheó, no seguimos con JSON
            if ($mode === 'ai') {

                $this->aiLogger->info('NlpPredispatch: pass-through (ai no match)', [
                    'q' => $qOriginal
                ]);
                return;
            }
        }

        // 3) JSON (o BOTH fallback)
        if (($route['type'] ?? '') === 'search') {
            $term = trim((string)($route['term'] ?? ''));
            $filters = (array)($route['filters'] ?? []);

            if ($term !== '' && (strcasecmp($term, $qOriginal) !== 0 || !empty($filters))) {

                $url = $this->urlBuilder->getUrl('catalogsearch/result/index', [
                    '_query' => (['q' => $term] + $filters)
                ]);

                $this->aiLogger->info('NlpPredispatch: json-redirect', [
                    'from' => $qOriginal,
                    'to' => $term,
                    'filters' => $filters,
                    'url' => $url
                ]);

                $this->redirect->redirect($this->response, $url);
                return;
            }
        }

        $this->aiLogger->info('NlpPredispatch: pass-through', [
            'q' => $qOriginal,
            'mode' => $mode
        ]);
    }
}
