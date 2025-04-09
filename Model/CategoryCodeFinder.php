<?php
declare(strict_types=1);

namespace Atelier\MOSSetup\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\Category;

class CategoryCodeFinder
{
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory
    ) {}

    /**
     * Find category by custom code attribute
     *
     * @param string $code
     * @return Category|null
     * @throws LocalizedException
     */
    public function findCategoryByCode(string $code): ?Category
    {
        $collection = $this->categoryCollectionFactory->create();
        
        // Add filter for the custom category code attribute
        $collection->addAttributeToFilter('category_code', $code);
        
        // Limit to one result
        $collection->setPageSize(1);

        // Return the first matching category or null
        return $collection->getFirstItem() && $collection->getFirstItem()->getId() 
            ? $collection->getFirstItem() 
            : null;
    }
}