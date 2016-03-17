<?php

/**
 * This file is part of the Spryker Demoshop.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Pyz\Zed\Collector\Business\Storage;

use Everon\Component\Collection\Collection;
use Orm\Zed\Category\Persistence\Map\SpyCategoryTableMap;
use Orm\Zed\Category\Persistence\SpyCategoryNode;
use Orm\Zed\Price\Persistence\Map\SpyPriceTypeTableMap;
use Orm\Zed\Price\Persistence\SpyPriceProductQuery;
use Orm\Zed\ProductCategory\Persistence\SpyProductCategory;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Formatter\ArrayFormatter;
use Pyz\Zed\Collector\CollectorConfig;
use Spryker\Shared\Product\ProductConstants;
use Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface;
use Spryker\Zed\Collector\Business\Collector\Storage\AbstractStoragePdoCollector;
use Spryker\Zed\Price\Business\PriceFacadeInterface;
use Spryker\Zed\ProductCategory\Persistence\ProductCategoryQueryContainerInterface;

class ProductCollector extends AbstractStoragePdoCollector
{

    const ID_PRODUCT = 'id_product';
    const ID_CATEGORY_NODE = 'id_category_node';
    const SKU = 'sku';
    const ABSTRACT_SKU = 'abstract_sku';
    const ABSTRACT_NAME = 'abstract_name';
    const ABSTRACT_URL = 'abstract_url';
    const QUANTITY = 'quantity';
    const ABSTRACT_LOCALIZED_ATTRIBUTES = 'abstract_localized_attributes';
    const CONCRETE_LOCALIZED_ATTRIBUTES = 'concrete_localized_attributes';
    const CONCRETE_ATTRIBUTES = 'concrete_attributes';
    const NAME = 'name';
    const PRICE = 'price';
    const PRICE_NAME = 'price_name';

    /**
     * @var \Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface
     */
    protected $categoryQueryContainer;

    /**
     * @var \Spryker\Zed\ProductCategory\Persistence\ProductCategoryQueryContainerInterface
     */
    protected $productCategoryQueryContainer;

    /**
     * @var \Spryker\Zed\Price\Business\PriceFacadeInterface
     */
    protected $priceFacade;

    /**
     * @var \Everon\Component\Collection\CollectionInterface
     */
    protected $categoryCacheCollection;

    /**
     * @param \Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface $categoryQueryContainer
     * @param \Spryker\Zed\ProductCategory\Persistence\ProductCategoryQueryContainerInterface $productCategoryQueryContainer
     * @param \Spryker\Zed\Price\Business\PriceFacadeInterface $priceFacade
     */
    public function __construct(
        CategoryQueryContainerInterface $categoryQueryContainer,
        ProductCategoryQueryContainerInterface $productCategoryQueryContainer,
        PriceFacadeInterface $priceFacade
    ) {
        $this->categoryQueryContainer = $categoryQueryContainer;
        $this->productCategoryQueryContainer = $productCategoryQueryContainer;
        $this->priceFacade = $priceFacade;
        $this->categoryCacheCollection = new Collection([]);
    }

    /**
     * @param string $touchKey
     * @param array $collectItemData
     *
     * @return array
     */
    protected function collectItem($touchKey, array $collectItemData)
    {
        return [
            'abstract_product_id' => $collectItemData[CollectorConfig::COLLECTOR_RESOURCE_ID],
            'abstract_attributes' => $this->getAbstractAttributes($collectItemData),
            'abstract_name' => $collectItemData[self::ABSTRACT_NAME],
            'abstract_sku' => $collectItemData[self::ABSTRACT_SKU],
            'url' => $collectItemData[self::ABSTRACT_URL],
            'quantity' =>  (int)$collectItemData[self::QUANTITY],
            'available' => (int)$collectItemData[self::QUANTITY] > 0,
            'prices' => $this->getPrices($collectItemData),
            'categories' => $this->generateCategories($collectItemData[CollectorConfig::COLLECTOR_RESOURCE_ID]),
        ];
    }

    /**
     * @return string
     */
    protected function collectResourceType()
    {
        return ProductConstants::RESOURCE_TYPE_PRODUCT_ABSTRACT;
    }

    /**
     * @param array $collectItemData
     *
     * @return array
     */
    protected function getAbstractAttributes(array $collectItemData)
    {
        $abstractLocalizedAttributesData = json_decode($collectItemData[self::ABSTRACT_LOCALIZED_ATTRIBUTES], true);
        $concreteLocalizedAttributesData = json_decode($collectItemData[self::CONCRETE_LOCALIZED_ATTRIBUTES], true);
        $concreteAttributesData = json_decode($collectItemData[self::CONCRETE_ATTRIBUTES], true);

        $attributes = array_merge($abstractLocalizedAttributesData, $concreteLocalizedAttributesData, $concreteAttributesData);

        return $attributes;
    }

    /**
     * @param string $sku
     *
     * @return int
     */
    protected function getValidPriceBySku($sku)
    {
        return $this->priceFacade->getPriceBySku($sku);
    }

    /**
     *   "prices": {
     *       "DEFAULT": {
     *          "price": "599"
     *       }
     *   },
     *   },
     *
     * @param array $collectItemData
     *
     * @return array
     */
    protected function getPrices($collectItemData)
    {
        $result = [];
        $query = SpyPriceProductQuery::create()
            ->setFormatter(new ArrayFormatter())
            ->joinProduct('productConcreteJoin')
            ->joinPriceType()
            ->withColumn(SpyPriceTypeTableMap::COL_NAME, self::PRICE_NAME)
            ->addJoinCondition(
                'productConcreteJoin',
                'productConcreteJoin.is_active = ?',
                true,
                Criteria::EQUAL
            )
            ->addJoinCondition(
                'productConcreteJoin',
                'productConcreteJoin.fk_product_abstract = ?',
                (int)$collectItemData[CollectorConfig::COLLECTOR_RESOURCE_ID]
            )
            ->where(
                'productConcreteJoin.id_product = ?',
                (int)$collectItemData[self::ID_PRODUCT]
            );

        $prices = $query->find();
        $data = $prices->toArray();

        if (empty($data)) {
            return $this->getDefaultPrice($collectItemData[self::ABSTRACT_SKU]);
        }

        foreach ($data as $priceItem) {
            $result[$priceItem[self::PRICE_NAME]] = [self::PRICE => $priceItem[self::PRICE]];
        }

        return $result;
    }

    /**
     * "DEFAULT": {
     *    "price": "599"
     * }
     *
     * @param $abstractSku
     *
     * @return array
     */
    protected function getDefaultPrice($abstractSku)
    {
        $defaultPriceType = $this->priceFacade->getDefaultPriceTypeName();
        $abstractPrice = $this->getValidPriceBySku($abstractSku);

        return [
            $defaultPriceType => [
                self::PRICE => $abstractPrice
            ]
        ];
    }

    /**
     * @param int $idProductAbstract
     *
     * @return array
     */
    protected function generateCategories($idProductAbstract)
    {
        if ($this->categoryCacheCollection->has($idProductAbstract)) {
            return $this->categoryCacheCollection->get($idProductAbstract);
        }

        $productCategoryMappings = $this->getProductCategoryMappings($idProductAbstract);

        $categories = [];
        foreach ($productCategoryMappings as $mapping) {
            $categories = $this->generateProductCategoryData($mapping, $categories);
        }

        $this->categoryCacheCollection->set($idProductAbstract, $categories);

        return $categories;
    }

    /**
     * @param \Orm\Zed\ProductCategory\Persistence\SpyProductCategory $productCategory
     * @param array $productCategoryCollection
     *
     * @return array
     */
    protected function generateProductCategoryData(SpyProductCategory $productCategory, array $productCategoryCollection)
    {
        foreach ($productCategory->getSpyCategory()->getNodes() as $node) {
            $queryPath = $this->categoryQueryContainer->queryPath($node->getIdCategoryNode(), $this->locale->getIdLocale());
            $pathTokens = $queryPath->find();

            $productCategoryCollection = $this->generateCategoryData($pathTokens, $productCategoryCollection);
        }

        return $productCategoryCollection;
    }

    /**
     * @param array $pathTokens
     * @param array $productCategoryCollection
     *
     * @return array
     */
    protected function generateCategoryData(array $pathTokens, array $productCategoryCollection)
    {
        foreach ($pathTokens as $pathItem) {
            $idNode = (int)$pathItem[self::ID_CATEGORY_NODE];
            $url = $this->generateUrl($idNode);

            $productCategoryCollection[$idNode] = [
                'node_id' => $idNode,
                'name' => $pathItem[self::NAME],
                'url' => $url,
            ];
        }

        return $productCategoryCollection;
    }

    /**
     * @param int $idProductAbstract
     *
     * @return \Orm\Zed\ProductCategory\Persistence\SpyProductCategory[]|\Propel\Runtime\Collection\ObjectCollection
     */
    protected function getProductCategoryMappings($idProductAbstract)
    {
        return $this->productCategoryQueryContainer
            ->queryLocalizedProductCategoryMappingByIdProduct($idProductAbstract)
            ->innerJoinSpyCategory()
            ->addAnd(
                SpyCategoryTableMap::COL_IS_ACTIVE,
                true,
                Criteria::EQUAL
            )
            ->orderByProductOrder()
            ->find();
    }

    /**
     * @param int $idNode
     *
     * @return null|string
     */
    protected function generateUrl($idNode)
    {
        $urlQuery = $this->categoryQueryContainer->queryUrlByIdCategoryNode($idNode);
        $url = $urlQuery->findOne();
        return ($url) ? $url->getUrl() : null;
    }

    /**
     * @param \Orm\Zed\Category\Persistence\SpyCategoryNode $node
     *
     * @return string
     */
    protected function buildPath(SpyCategoryNode $node)
    {
        $pathTokens = $this->categoryQueryContainer
            ->queryPath($node->getIdCategoryNode(), $this->locale->getIdLocale(), false, true)
            ->find();

        $formattedPath = [];
        foreach ($pathTokens as $path) {
            $formattedPath[] = $path[self::NAME];
        }

        return '/' . implode('/', $formattedPath);
    }

    /**
     * @return \Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface
     */
    public function getCategoryQueryContainer()
    {
        return $this->categoryQueryContainer;
    }

    /**
     * @param \Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface $categoryQueryContainer
     *
     * @return void
     */
    public function setCategoryQueryContainer(CategoryQueryContainerInterface $categoryQueryContainer)
    {
        $this->categoryQueryContainer = $categoryQueryContainer;
    }

    /**
     * @return \Spryker\Zed\ProductCategory\Persistence\ProductCategoryQueryContainerInterface
     */
    public function getProductCategoryQueryContainer()
    {
        return $this->productCategoryQueryContainer;
    }

    /**
     * @param \Spryker\Zed\ProductCategory\Persistence\ProductCategoryQueryContainerInterface $productCategoryQueryContainer
     *
     * @return void
     */
    public function setProductCategoryQueryContainer(ProductCategoryQueryContainerInterface $productCategoryQueryContainer)
    {
        $this->productCategoryQueryContainer = $productCategoryQueryContainer;
    }

}
