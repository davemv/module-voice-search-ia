<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class IntentMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'json', 'label' => __('Usar JSON')],
            ['value' => 'ai',   'label' => __('Usar IA')],
            ['value' => 'both', 'label' => __('JSON + IA (fallback)')],
        ];
    }
}
