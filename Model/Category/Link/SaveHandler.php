<?php

namespace SemExpert\ProductDuplicationFixM221\Model\Category\Link;

class SaveHandler extends \Magento\Catalog\Model\Category\Link\SaveHandler
{
    private $productCategoryLink;
    private $hydratorPool;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CategoryLink $productCategoryLink,
        \Magento\Framework\EntityManager\HydratorPool $hydratorPool
    ) {
        parent::__construct($productCategoryLink, $hydratorPool);

        $this->hydratorPool = $hydratorPool;
        $this->productCategoryLink = $productCategoryLink;
    }

    public function execute($entity, $arguments = [])
    {
        $entity->setIsChangedCategories(false);

        $extensionAttributes = $entity->getExtensionAttributes();
        if ($extensionAttributes === null && !$entity->hasCategoryIds()) {
            return $entity;
        }

        $modelCategoryLinks = $this->getCategoryLinksPositions($entity);

        $dtoCategoryLinks = $extensionAttributes->getCategoryLinks();
        if ($dtoCategoryLinks !== null) {
            $hydrator = $this->hydratorPool->getHydrator(CategoryLinkInterface::class);
            $dtoCategoryLinks = array_map(function ($categoryLink) use ($hydrator) {
                return $hydrator->extract($categoryLink) ;
            }, $dtoCategoryLinks);
            $processLinks = $this->mergeCategoryLinksCustom($dtoCategoryLinks, $modelCategoryLinks);
        } else {
            $processLinks = $modelCategoryLinks;
        }

        $affectedCategoryIds = $this->productCategoryLink->saveCategoryLinks($entity, $processLinks);

        if (!empty($affectedCategoryIds)) {
            $entity->setAffectedCategoryIds($affectedCategoryIds);
            $entity->setIsChangedCategories(true);
        }

        return $entity;
    }

    /**
     * @param object $entity
     * @return array
     */
    private function getCategoryLinksPositions($entity)
    {
        $result = [];
        $currentCategoryLinks = $this->productCategoryLink->getCategoryLinks($entity, $entity->getCategoryIds());
        foreach ($entity->getCategoryIds() as $categoryId) {
            $key = array_search($categoryId, array_column($currentCategoryLinks, 'category_id'));
            if ($key === false) {
                $result[] = ['category_id' => (int)$categoryId, 'position' => 0];
            } else {
                $result[] = $currentCategoryLinks[$key];
            }
        }

        return $result;
    }


    /**
     * Merge category links
     *
     * @param array $newCategoryPositions
     * @param array $oldCategoryPositions
     * @return array
     */
    private function mergeCategoryLinksCustom($newCategoryPositions, $oldCategoryPositions)
    {
        $result = [];
        if (empty($newCategoryPositions)) {
            return $result;
        }

        foreach ($newCategoryPositions as $newCategoryPosition) {
            $key = array_search(
                $newCategoryPosition['category_id'],
                array_column($oldCategoryPositions, 'category_id')
            );

            if ($key === false) {
                $result[] = $newCategoryPosition;
            } elseif (isset($oldCategoryPositions[$key])) {
                if (intval($oldCategoryPositions[$key]['position']) != $newCategoryPosition['position']) {
                    $result[] = $newCategoryPositions[$key];
                    unset($oldCategoryPositions[$key]);
                }
            }
        }
        $result = array_merge($result, $oldCategoryPositions);

        return $result;
    }
}
