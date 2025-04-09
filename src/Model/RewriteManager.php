<?php

declare(strict_types=1);

namespace Atelier\MosSetup\Model;

use Atelier\MosSetup\Logger\CustomLogger;
use Atelier\MosSetup\Helper\SecureContextExecutor;

use Magento\UrlRewrite\Model\ResourceModel\UrlRewrite as UrlRewriteResource;
use Magento\UrlRewrite\Model\UrlRewrite;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;

class RewriteManager
{
    public function __construct(
        private readonly UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        private readonly UrlRewriteResource $urlRewriteResource,
        private readonly CustomLogger $logger,
        private readonly SecureContextExecutor $secureContextExecutor
    ) {}

    public function cleanCategoryUrlRewrites(): void
    {
        $this->secureContextExecutor->execute(function (): void {
            try {
                $collection = $this->urlRewriteCollectionFactory->create();
                $collection->addFieldToFilter('entity_type', 'category');
                
                $deletedCount = 0;

                /** @var UrlRewrite $urlRewrite */
                foreach ($collection as $urlRewrite) {
                    $this->urlRewriteResource->delete($urlRewrite);
                    $deletedCount++;
                }

                $this->logger->info('Se han borrado correctamente URL rewrites de categoría', [
                    'borrados' => $deletedCount
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Error al eliminar URL rewrites de categoría', [
                    'error_message' => $e->getMessage()
                ]);
            }
        });
    }
}
