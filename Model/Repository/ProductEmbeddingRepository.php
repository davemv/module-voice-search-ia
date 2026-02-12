<?php
declare(strict_types=1);

namespace NTT\VoiceSearch\Model\Repository;

use Magento\Framework\App\ResourceConnection;

class ProductEmbeddingRepository
{
    private string $tableName;

    public function __construct(private ResourceConnection $resource)
    {
        $this->tableName = $this->resource->getTableName('ntt_product_embeddings');
    }

    public function saveEmbedding(int $productId, array $vector): void
    {
        $conn = $this->resource->getConnection();
        $data = [
            'product_id' => $productId,
            'vector' => json_encode($vector),
            'vector_dimensions' => count($vector),
            'updated_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        $conn->insertOnDuplicate($this->tableName, $data, ['vector', 'vector_dimensions', 'updated_at']);
    }

    public function getEmbedding(int $productId): ?array
    {
        $conn = $this->resource->getConnection();
        $select = $conn->select()->from($this->tableName, ['vector'])->where('product_id = ?', $productId);
        $row = $conn->fetchOne($select);
        if (!$row) return null;

        $vec = json_decode($row, true);
        return is_array($vec) ? $vec : null;
    }

    public function getEmbeddings(array $productIds): array
    {
        if (empty($productIds)) return [];

        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from($this->tableName, ['product_id', 'vector'])
            ->where('product_id IN (?)', $productIds);

        $rows = $conn->fetchAll($select);
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['product_id']] = json_decode((string)$r['vector'], true);
        }

        foreach ($productIds as $id) {
            $id = (int)$id;
            if (!array_key_exists($id, $out)) $out[$id] = null;
        }

        return $out;
    }

    public function getProductIds(int $limit = 200): array
    {
        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from($this->tableName, ['product_id'])
            ->limit($limit);

        return array_map('intval', $conn->fetchCol($select));
    }

    /**
     * Filtra candidatos por website para evitar que luego la colección quede vacía.
     */
    public function getProductIdsForWebsite(int $websiteId, int $limit = 500): array
    {
        $conn = $this->resource->getConnection();

        $cpw = $conn->getTableName('catalog_product_website');

        $select = $conn->select()
            ->from(['e' => $this->tableName], ['product_id'])
            ->join(['w' => $cpw], 'w.product_id = e.product_id', [])
            ->where('w.website_id = ?', $websiteId)
            ->limit($limit);

        return array_map('intval', $conn->fetchCol($select));
    }
}
