<?php

declare(strict_types=1);

namespace Atelier\MOSSetup\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Indexer\Model\Indexer\CollectionFactory;

class SystemCleanManager
{
    public function __construct(
        private readonly TypeListInterface $cacheTypeList,
        private readonly CacheManager $cacheManager,
        private readonly CollectionFactory $indexerCollectionFactory
    ) {
    }

    /**
     * Reindexa todo
     *
     */
    public function reindex(): void
    {
        $indexers = $this->indexerCollectionFactory->create()->getItems();
        foreach ($indexers as $indexer) {
            try {
                // Reindexa cada indexador individualmente
                $indexer->reindexAll();
                
            } catch (\Exception $e) {
                error_log("Error al indexar: " . $e->getMessage());
            }
        }
    }

    /**
     * Limpia tipos específicos de caché.
     */
    public function cleanCache(array $types = []): void
    {
        if (empty($types)) {
            $types = [
                'config',
                'collections',
                'layout',
                'block_html',
                'full_page',
                'reflection',
                'db_ddl',
            ];
        }

        foreach ($types as $type) {
            try {
                $this->cacheTypeList->cleanType($type);
            } catch (\Exception $e) {
                error_log("Error al limpiar caché $type: " . $e->getMessage());
            }
        }

        try {
            $this->cacheManager->flush($types);
        } catch (\Exception $e) {
            error_log("Error al hacer flush de caché: " . $e->getMessage());
        }
    }
}
