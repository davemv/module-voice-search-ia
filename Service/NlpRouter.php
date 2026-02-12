<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Service;

use NTT\VoiceSearch\Model\Nlp\QueryParser;
use NTT\VoiceSearch\Model\Nlp\Config;
use Magento\Store\Model\StoreManagerInterface;

class NlpRouter
{
    public function __construct(
        private QueryParser $parser,
        private Config $config,
        private StoreManagerInterface $storeManager,
        private IntentRegistry $intents
    ) {}

    /** Normalize casing + acentos para comparar */
    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
        return strtr($s, $map);
    }

    public function route(string $q): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $qNorm   = $this->norm($q);

        /* =============================
         * 1) INTENTS (DB / Redirects duros)
         * =============================*/
        if ($target = $this->intents->match($q)) {
            return [
                'type' => 'redirect',
                'url'  => $target,
                'raw'  => $q
            ];
        }

        /* =============================
         * 2) REDIRECTS JSON (si existen)
         * =============================*/
        foreach ($this->config->getRedirects($storeId) as $rule) {

            $matchType = $rule['match'] ?? 'eq';
            $url       = $rule['url'] ?? null;
            $phrases   = $rule['phrases'] ?? [];

            if (!$url || !is_array($phrases)) {
                continue;
            }

            foreach ($phrases as $p) {
                $pNorm = $this->norm($p);

                $match = match ($matchType) {
                    'eq'       => $qNorm === $pNorm,
                    'contains' => str_contains($qNorm, $pNorm),
                    'regex'    => @preg_match("/{$p}/iu", $q) === 1,
                    default    => false
                };

                if ($match) {
                    return [
                        'type' => 'redirect',
                        'url'  => $url,
                        'raw'  => $q
                    ];
                }
            }
        }

        /* =============================
         * 3) SEARCH → NO IA PARSE
         * =============================
         * Dejamos el término EXACTO como lo escribió el usuario.
         * Si Magento no encuentra nada,
         * el plugin InjectSemanticResultsPlugin hará el fallback semántico.
         */
        $parsed = $this->parser->parse($q);

        return [
            'type'    => 'search',
            'term'    => (string)($parsed['term'] ?? $q),
            'filters' => (array)($parsed['filters'] ?? []),
            'raw'     => $q
        ];
            }
}
