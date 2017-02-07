<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@totalinternetgroup.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@totalinternetgroup.nl for more information.
 *
 * @copyright   Copyright (c) 2017 Total Internet Group B.V. (http://www.totalinternetgroup.nl)
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\PostNL\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Checkout\Model\Session;
use \Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Quote\Model\Quote\Item as QuoteItem;

class QuoteItemsAreInStock
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var null
     */
    private $itemsAreInStock = null;

    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * @param CheckoutSession             $checkoutSession
     * @param StockRegistryInterface      $stockRegistryInterface
     * @param StockConfigurationInterface $stockConfiguration
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        StockRegistryInterface $stockRegistryInterface,
        StockConfigurationInterface $stockConfiguration
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->stockRegistry = $stockRegistryInterface;
        $this->stockConfiguration = $stockConfiguration;
    }

    /**
     * @return bool
     */
    public function getValue()
    {
        $quote = $this->checkoutSession->getQuote();
        $items = $quote->getAllItems();

        if ($this->stockConfiguration->getBackorders() == 0) {
            return true;
        }

        return $this->itemsAreInStock($items);
    }

    /**
     * Loop over the items and remove all items that have stock. If there are any items left, it means that not all
     * items are in stock so we return false.
     *
     * @param QuoteItem[] $items
     *
     * @return bool
     */
    private function itemsAreInStock($items)
    {
        if ($this->itemsAreInStock !== null) {
            return $this->itemsAreInStock;
        }

        $items = array_filter($items, function (QuoteItem $item) {
            $product = $item->getProduct();

            if ($product->getTypeId() != 'simple') {
                return false;
            }

            return !$this->isItemInStock($item);
        });

        $this->itemsAreInStock = empty($items);
        return $this->itemsAreInStock;
    }

    private function isItemInStock(QuoteItem $item)
    {
        $product = $item->getProduct();

        /** @noinspection PhpUndefinedMethodInspection */
        $stockItem = $this->stockRegistry->getStockItem($product->getId(), $product->getStoreId());

        if ($stockItem->getUseConfigBackorders()
            || (
                !$stockItem->getUseConfigBackorders() &&
                $stockItem->getBackorders() == 0
            )
        ) {
            return true;
        }

        if (!$stockItem->getUseConfigMinQty()) {
            $minQty = $stockItem->getMinQty();
        } else {
            $minQty = $this->stockConfiguration->getMinQty();
        }

        /**
         * Check if the product has the required qty available.
         */
        $requiredQty = $item->getParentItem() ? $item->getParentItem()->getQty() : $item->getQty();
        if (($stockItem->getQty() - $minQty) < $requiredQty) {
            return false;
        }

        return false;
    }
}
