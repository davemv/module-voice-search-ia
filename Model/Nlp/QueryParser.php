<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Nlp;

use Magento\Store\Model\StoreManagerInterface;

final class QueryParser
{
    public function __construct(
        private StoreManagerInterface $storeManager,
        private Config $config,
        private AttributeResolver $attributeResolver
    ) {}

    /** @return array{term:string, filters:array, raw:string} */
    public function parse(string $q): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $qNorm   = mb_strtolower(trim($q));

        // 1) Cargamos datos desde el JSON (product_type, attributes, stopwords)
        $stopwords = $this->config->getStopwords($storeId);           // ['me','gustaria',...]
        $productMap = $this->config->getProductTypeMap($storeId);     // ['jacket' => [...]]
        $colorCode  = $this->config->getColorAttributeCode($storeId); // 'color' o null
        $colorMap   = $colorCode ? $this->config->getAttributeValueMap($storeId, $colorCode) : [];

        // 2) Tokenizamos y filtramos stopwords
        $tokens = array_values(array_filter(
            preg_split('/[\s,.\-+\/]+/u', $qNorm) ?: [],
            fn($t) => $t !== '' && !in_array($t, $stopwords, true)
        ));

        // 3) Indexamos sinónimos para lookup rápido
        //    product_type: palabra normalizada -> canónico
        $productIndex = [];
        foreach ($productMap as $canonical => $variants) {
            $canonical = (string)$canonical;
            $canonNorm = mb_strtolower(trim($canonical));
            if ($canonNorm !== '') {
                $productIndex[$canonNorm] = $canonical;
            }
            if (is_array($variants)) {
                foreach ($variants as $v) {
                    $vn = mb_strtolower(trim((string)$v));
                    if ($vn !== '') {
                        $productIndex[$vn] = $canonical;
                    }
                }
            }
        }

        //    color: palabra normalizada -> label canónico del atributo (ej "Blue")
        $colorIndex = [];
        foreach ($colorMap as $canonical => $variants) {
            $canonical = (string)$canonical; // "Blue"
            $canonNorm = mb_strtolower(trim($canonical)); // "blue"
            if ($canonNorm !== '') {
                $colorIndex[$canonNorm] = $canonical;
            }
            if (is_array($variants)) {
                foreach ($variants as $v) {
                    $vn = mb_strtolower(trim((string)$v));
                    if ($vn !== '') {
                        $colorIndex[$vn] = $canonical;
                    }
                }
            }
        }

        // 4) Recorremos tokens para encontrar:
        //    - tipo de producto (head)
        //    - color
        $head = null;
        $colorCanonical = null;

        foreach ($tokens as $t) {
            $tn = mb_strtolower($t);

            if (!$head && isset($productIndex[$tn])) {
                $head = $productIndex[$tn]; // ej: "jacket" / "tee"
            }

            if ($colorCode && !$colorCanonical && isset($colorIndex[$tn])) {
                $colorCanonical = $colorIndex[$tn]; // ej: "Blue"
            }
        }

        // Si no encontramos un tipo explícito, elegir el primer token que NO sea atributo (p.ej. no color)
        if (!$head && !empty($tokens)) {
            $firstNonAttr = null;
            foreach ($tokens as $t) {
                $tn = mb_strtolower($t);
                $isColor = $colorCode ? isset($colorIndex[$tn]) : false;
                if (!$isColor) {
                    $firstNonAttr = $t;
                    break;
                }
            }
            $head = $firstNonAttr ?: $tokens[0];
        }


        // 5) Construimos filtros (por ahora solo color)
        $filters = [];
        if ($colorCanonical && $colorCode) {
            $optionId = $this->attributeResolver->colorLabelToOptionId(
                $storeId,
                $colorCanonical   // label EXACTA del atributo, ej "Blue"
            );
            if ($optionId) {
                $filters[$colorCode] = $optionId;
            }
        }

        return [
            'term'    => $head ?: $qNorm,
            'filters' => $filters,
            'raw'     => $q
        ];
    }
}
