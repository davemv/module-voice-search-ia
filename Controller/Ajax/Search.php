<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;

class Search extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $resultJsonFactory,
        private ResourceConnection $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $query = trim((string) $this->getRequest()->getParam('voice_query', ''));
        if ($query === '') {
            return $result->setData(['success' => false, 'message' => 'No input']);
        }

        $conn = $this->resource->getConnection();
        $table = $conn->getTableName('voice_search_corrections');

        // 1) Cache DB
        $hit = $conn->fetchOne(
            $conn->select()->from($table, ['corrected_term'])->where('raw_term = ?', $query)
        );
        if ($hit) {
            return $result->setData(['success' => true, 'corrected' => $hit, 'source' => 'cache']);
        }

        // 2) OpenAI si existe API key
        $apiKey = (string) getenv('OPENAI_API_KEY');
        if ($apiKey !== '') {
            try {
                $payload = json_encode([
                    'model' => 'gpt-3.5-turbo',
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Sos un normalizador de consultas de e-commerce. Corrigí errores ortográficos/fonéticos ("siaomi"→"xiaomi"). Devolvé SOLO el término corregido.'],
                        ['role' => 'user', 'content' => $query]
                    ]
                ], JSON_UNESCAPED_UNICODE);

                $ch = curl_init('https://api.openai.com/v1/chat/completions');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $apiKey,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                ]);
                $resp = curl_exec($ch);
                if ($resp === false) {
                    throw new \RuntimeException('OpenAI error: ' . curl_error($ch));
                }
                $data = json_decode($resp, true);
                $aiText = trim((string)($data['choices'][0]['message']['content'] ?? ''));
                if ($aiText !== '') {
                    $conn->insertOnDuplicate($table, [
                        'raw_term' => $query,
                        'corrected_term' => $aiText
                    ], ['corrected_term']);
                    return $result->setData(['success' => true, 'corrected' => $aiText, 'source' => 'openai']);
                }
            } catch (\Throwable $e) {
                // seguir a fallback
            }
        }

        // 3) Fallback local
        $dictionary = [
            'xiaomi','samsung','motorola','iphone','huawei','nokia','lg','realme','oppo','vivo',
            'redmi','poco','moto g','galaxy','note','apple watch','airpods'
        ];
        $rules = [
            'siaomi' => 'xiaomi',
            'chaiomi' => 'xiaomi',
            'siomi'  => 'xiaomi',
            'samgung'=> 'samsung',
            'samsum' => 'samsung',
            'motog'  => 'moto g',
            'ai fone'=> 'iphone',
        ];
        $lower = mb_strtolower($query);
        $corrected = $rules[$lower] ?? $this->closestByLevenshtein($lower, $dictionary);

        if ($corrected) {
            $conn->insertOnDuplicate($table, [
                'raw_term' => $query,
                'corrected_term' => $corrected
            ], ['corrected_term']);
            return $result->setData(['success' => true, 'corrected' => $corrected, 'source' => 'fallback']);
        }

        return $result->setData(['success' => true, 'corrected' => $query, 'source' => 'raw']);
    }

    private function closestByLevenshtein(string $needle, array $haystack): ?string
    {
        $min = PHP_INT_MAX; $best = null;
        foreach ($haystack as $word) {
            $dist = levenshtein($needle, mb_strtolower($word));
            if ($dist < $min) { $min = $dist; $best = $word; }
        }
        return ($min <= 3) ? $best : null;
    }
}
