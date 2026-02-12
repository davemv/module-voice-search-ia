<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Nlp;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class RuleEngine
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private StoreManagerInterface $storeManager
    ) {}

    public function apply(string $query): array
    {
        $rows = $this->loadRows();
        $q = $this->normalize($query);

        $select = []; $numeric = []; $rewrite = null;

        foreach ($rows as $r) {
            $triggers = array_filter(array_map('trim', explode(',', (string)($r['triggers'] ?? ''))));
            if (!$triggers) continue;

            // match contains/equals
            $matchType = (string)($r['match_type'] ?? 'contains');
            $matched = false;
            foreach ($triggers as $t) {
                $t = $this->normalize($t);
                if ($t === '') continue;
                if ($matchType === 'equals') {
                    if (preg_match('/\b'.preg_quote($t,'/').'\b/u', $q)) { $matched = true; break; }
                } else {
                    if (str_contains($q, $t)) { $matched = true; break; }
                }
            }
            if (!$matched) continue;

            $attr  = trim((string)($r['attr_code'] ?? ''));  // <— puede venir vacío
            $type  = (string)($r['filter_type'] ?? 'select');
            $op    = (string)($r['operator'] ?? '>=');
            $value = trim((string)($r['value'] ?? ''));

            if ($value === '') continue;

            if ($attr === '') {
                // 💡 si no hay atributo, interpretamos como REWRITE del término
                $rewrite = $value;
                continue;
            }

            if ($type === 'numeric') {
                $v = (float)str_replace(',', '.', $value);
                $numeric[$attr] = ['op' => $op, 'value' => $v];
            } else {
                // select/multiselect por label
                $select[$attr] = $value;
            }
        }

        return ['rewrite' => $rewrite, 'select' => $select, 'numeric' => $numeric];
    }

    /** compat: si en algún lado llamabas filtersFor() */
    public function filtersFor(string $query): array
    {
        $a = $this->apply($query);
        return ['select' => $a['select'], 'numeric' => $a['numeric']];
    }

    private function loadRows(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $raw = (string)$this->scopeConfig->getValue('ntt_voice/nlp/rules_rows', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        if ($raw === '') return [];

        // 1) Intentar unserialize (Magento serializa arrays)
        try {
            $serializer = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Serialize\SerializerInterface::class);
            $arr = $serializer->unserialize($raw);
            if (is_array($arr)) return $arr;
        } catch (\Throwable $e) {}

        // 2) Si por algún motivo es JSON, soportarlo igual
        try {
            $arr = json_decode($raw, true);
            if (is_array($arr)) return $arr;
        } catch (\Throwable $e) {}

        return [];
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $tr = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
        $s = strtr($s, $tr);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }
}
