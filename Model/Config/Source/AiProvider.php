<?php
declare(strict_types=1);
namespace NTT\VoiceSearch\Model\Config\Source;

class AiProvider
{
    public function toOptionArray(): array {
        return [
            ['value'=>'openai','label'=>__('OpenAI (Embeddings)')],
            ['value'=>'mock','label'=>__('Mock (demo, sin API)')],
        ];
    }
}
