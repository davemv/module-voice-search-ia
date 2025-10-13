<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model;

use Magento\Framework\App\ResourceConnection;

class Corrector
{
    public function __construct(private ResourceConnection $resource) {}

    /**
     * Devuelve el término corregido si hay match; si no, null.
     * Usa: DB cache y fallback simples (igual que en tu Ajax controller).
     */
    public function correct(string $query): ?string
    {
        $query = trim($query);
        if ($query === '') return null;

        $conn = $this->resource->getConnection();
        $table = $conn->getTableName('voice_search_corrections');

        // 1) cache DB exacta
        $hit = $conn->fetchOne(
            $conn->select()->from($table, ['corrected_term'])->where('raw_term = ?', $query)
        );
        if ($hit) return $hit;

        // 2) fallback local básico (podés mejorar/leer desde CSV)
        $dictionary = [
            'xiaomi','samsung','motorola','iphone','huawei','nokia','lg','realme','oppo','vivo',
            'redmi','poco','moto g','galaxy','note','apple watch','airpods','jacket','hoodie','shorts'
        ];
        $rules = [
            'siaomi'=>'xiaomi','chaiomi'=>'xiaomi','siomi'=>'xiaomi',
            'samgung'=>'samsung','samsum'=>'samsung',
            'motog'=>'moto g','ai fone'=>'iphone',
            'gack'=>'jacket','gacket'=>'jacket' // 👈 ejemplo que pediste
        ];
        $lower = mb_strtolower($query);
        if (isset($rules[$lower])) return $rules[$lower];

        $closest = $this->closestByLevenshtein($lower, $dictionary);
        return $closest;
    }

    private function closestByLevenshtein(string $needle, array $haystack): ?string
    {
        $min = PHP_INT_MAX; $best = null;
        foreach ($haystack as $word) {
            $dist = levenshtein($needle, mb_strtolower($word));
            if ($dist < $min) { $min = $dist; $best = $word; }
        }
        return ($min <= 2) ? $best : null; // umbral ajustable
    }
}
