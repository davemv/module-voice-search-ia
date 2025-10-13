<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Block;

use Magento\Framework\View\Element\Template;
use NTT\VoiceSearch\Model\Corrector;

class DidYouMean extends Template
{
    public function __construct(
        Template\Context $context,
        private Corrector $corrector,
        array $data = []
    ) { parent::__construct($context, $data); }

    public function getOriginalQuery(): string
    {
        return (string) $this->getRequest()->getParam('q', '');
    }

    public function getCorrection(): ?string
    {
        $orig = $this->getOriginalQuery();
        $corr = $this->corrector->correct($orig);
        if ($corr && mb_strtolower($corr) !== mb_strtolower($orig)) return $corr;
        return null;
    }

    protected function _toHtml()
    {
        $corr = $this->getCorrection();
        if (!$corr) return '';
        $url = $this->getUrl('catalogsearch/result', ['q' => $corr]);
        $corrEsc = $this->escapeHtml($corr);
        return '<div class="message notice didyoumean">'
             . '¿Quizás quisiste decir '
             . '<a href="'. $url .'"><strong>'. $corrEsc .'</strong></a>'
             . '?</div>';
    }
}
