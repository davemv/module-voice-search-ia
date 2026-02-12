<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Nlp;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const PATH_COLOR_ATTRIBUTE = 'ntt_voice/nlp/color_attribute';
    public const PATH_SYNONYMS_JSON   = 'ntt_voice/nlp/synonyms_json';

    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {}

    public function getColorAttributeCode(int $storeId): ?string
    {
        $code = (string)$this->scopeConfig->getValue(
            self::PATH_COLOR_ATTRIBUTE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $code = trim($code);

        return $code !== '' ? $code : null; // null si no está configurado
    }

    /**
     * Devuelve el JSON de sinónimos parseado (o [] si no hay nada / está mal).
     *
     * Formato sugerido:
     * {
     *   "product_type": { "campera": ["campera","jacket"] },
     *   "attributes": { "color": { "azul": ["azul","blue"] } },
     *   "stopwords": ["quiero","un","una","de","para"]
     * }
     */
    public function getSynonymsData(int $storeId): array
    {
        $json = (string)$this->scopeConfig->getValue(
            self::PATH_SYNONYMS_JSON,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($json === '') {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /** Stopwords normalizadas a minúsculas. */
    public function getStopwords(int $storeId): array
    {
        $data = $this->getSynonymsData($storeId);
        $stopwords = $data['stopwords'] ?? [];

        if (!is_array($stopwords)) {
            return [];
        }

        return array_values(array_unique(
            array_filter(
                array_map(
                    fn($w) => mb_strtolower(trim((string)$w)),
                    $stopwords
                ),
                fn($w) => $w !== ''
            )
        ));
    }

    /** Mapa de tipos de producto desde el JSON. */
    public function getProductTypeMap(int $storeId): array
    {
        $data = $this->getSynonymsData($storeId);
        $map = $data['product_type'] ?? [];
        return is_array($map) ? $map : [];
    }

    /** Mapa de valores para un atributo concreto (p.ej. "color"). */
    public function getAttributeValueMap(int $storeId, string $attributeCode): array
    {
        $data = $this->getSynonymsData($storeId);
        $all = $data['attributes'] ?? [];
        $map = $all[$attributeCode] ?? [];
        return is_array($map) ? $map : [];
    }

    /** Devuelve el JSON completo crudo */
    public function getFullConfig(int $storeId): array
    {
        return $this->getSynonymsData($storeId);
    }

    /** Obtiene recomendaciones */
    public function getRecommended(int $storeId): array
    {
        $data = $this->getSynonymsData($storeId);
        $rec  = $data['recommended'] ?? [];

        return [
            'title' => $rec['title'] ?? 'Recomendados',
            'items' => $rec['items'] ?? []
        ];
    }


    /** Obtiene redirect rules */
    public function getRedirects(int $storeId): array
    {
        $data = $this->getSynonymsData($storeId);
        $rows = $data['redirects'] ?? [];

        if (!is_array($rows)) return [];

        // Orden por prioridad ascendente
        usort($rows, fn($a,$b)=>($a['priority']??999) <=> ($b['priority']??999));

        return $rows;
    }
}
