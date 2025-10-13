<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Plugin;

use Magento\Search\Controller\Ajax\Suggest;
use NTT\VoiceSearch\Model\Corrector;
use Magento\Framework\Escaper;

class SuggestCorrectionPlugin
{
    private ?string $corrected = null;

    public function __construct(
        private Corrector $corrector,
        private Escaper $escaper
    ) {}

    /** Reescribimos 'q' de forma compatible con la interfaz (setParams) */
    public function beforeExecute(Suggest $subject): void
    {
        $request = $subject->getRequest();
        $orig = (string)$request->getParam('q', '');
        $corr = $this->corrector->correct($orig);

        if ($corr && strcasecmp($corr, $orig) !== 0) {
            $this->corrected = $corr;
            $params = $request->getParams();
            $params['q'] = $corr;
            $request->setParams($params); // ✅ existe en RequestInterface
        } else {
            $this->corrected = null;
        }
    }

    /** Inyectamos el “¿Quizás quisiste decir…?” de forma segura para varias versiones */
    public function afterExecute(Suggest $subject, $result)
    {
        if (!$this->corrected) {
            return $result;
        }

        $response = $subject->getResponse();

        // Obtener el body con el método disponible
        $body = null;
        if (method_exists($response, 'getBody')) {
            $body = (string)$response->getBody();
        } elseif (method_exists($response, 'getContent')) {
            $body = (string)$response->getContent();
        }

        if ($body && stripos($body, '<ul') !== false) {
            $safe = $this->escaper->escapeHtml($this->corrected);
            $url  = '/catalogsearch/result/?q=' . rawurlencode($this->corrected);

            $didYou = '<li class="suggest-correction">'
                    . '¿Quizás quisiste decir: '
                    . '<a href="'.$url.'">'.$safe.'</a>'
                    . '?</li>';

            $modified = preg_replace('/(<ul[^>]*>)/i', '$1' . $didYou, 1);

            // Setear el body con el método disponible
            if ($modified !== null) {
                if (method_exists($response, 'setBody')) {
                    $response->setBody($modified);
                } elseif (method_exists($response, 'setContent')) {
                    $response->setContent($modified);
                }
            }
        }

        $this->corrected = null;
        return $result;
    }
}
