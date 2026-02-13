<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

final class IntentRegistry
{
    private const CFG_PATH = 'ntt_voice/nlp/intents';

    public function __construct(
        private ScopeConfigInterface $cfg,
        private StoreManagerInterface $stores
    ) {}

    public function match(string $q): ?string
    {
        $storeId = (int)$this->stores->getStore()->getId();
        $raw = (string)$this->cfg->getValue(self::CFG_PATH, ScopeInterface::SCOPE_STORE, $storeId);
        $rows = $raw ? json_decode($raw, true) : [];
        if (!is_array($rows) || !$rows) { return null; }

        $qNorm = $this->norm($q);

        foreach ($rows as $r) {
            $mode   = (string)($r['match'] ?? 'eq');
            $target = (string)($r['target'] ?? '');
            $phr    = (array)($r['phrases'] ?? []);
            if ($target === '' || !$phr) { continue; }

            foreach ($phr as $p) {
                $pNorm = $this->norm((string)$p);
                $ok = match ($mode) {
                    'eq'       => $qNorm === $pNorm,
                    'contains' => $pNorm !== '' && str_contains($qNorm, $pNorm),
                    'regex'    => @preg_match('/'.$p.'/iu', $q) === 1, // usa texto crudo para regex
                    default    => false
                };
                if ($ok) { return $target; }
            }
        }
        return null;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
        return strtr($s, $map);
    }
}
