<?php
/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Mage_Sales_Model_Quote_Address_Total_Discount
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Quote_Address_Total_Discount extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    /**
     * @param Mage_Sales_Model_Quote_Address $address
     * @return $this
     * @throws Mage_Core_Model_Store_Exception
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $quote = $address->getQuote();
        $eventArgs = [
            'website_id' => Mage::app()->getStore($quote->getStoreId())->getWebsiteId(),
            'customer_group_id' => $quote->getCustomerGroupId(),
            'coupon_code' => $quote->getCouponCode(),
        ];

        $address->setFreeShipping(0);
        $totalDiscountAmount = 0;
        $subtotalWithDiscount = 0;
        $baseTotalDiscountAmount = 0;
        $baseSubtotalWithDiscount = 0;

        $items = $address->getAllItems();
        if (!count($items)) {
            $address->setDiscountAmount($totalDiscountAmount);
            $address->setSubtotalWithDiscount($subtotalWithDiscount);
            $address->setBaseDiscountAmount($baseTotalDiscountAmount);
            $address->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);
            return $this;
        }

        $hasDiscount = false;
        foreach ($items as $item) {
            if ($item->getNoDiscount()) {
                $item->setDiscountAmount(0);
                $item->setBaseDiscountAmount(0);
                $item->setRowTotalWithDiscount($item->getRowTotal());
                $item->setBaseRowTotalWithDiscount($item->getRowTotal());

                $subtotalWithDiscount += $item->getRowTotal();
                $baseSubtotalWithDiscount += $item->getBaseRowTotal();
            } else {
                /**
                 * Child item discount we calculate for parent
                 */
                if ($item->getParentItemId()) {
                    continue;
                }

                /**
                 * Composite item discount calculation
                 */

                if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                    foreach ($item->getChildren() as $child) {
                        $eventArgs['item'] = $child;
                        Mage::dispatchEvent('sales_quote_address_discount_item', $eventArgs);

                        if ($child->getDiscountAmount() || $child->getFreeShipping()) {
                            $hasDiscount = true;
                        }

                        /**
                         * Parent free shipping we apply to all children
                         */
                        if ($item->getFreeShipping()) {
                            $child->setFreeShipping($item->getFreeShipping());
                        }

                        /**
                         * @todo Parent discount we apply for all children without discount
                         */
                        if (!$child->getDiscountAmount() && $item->getDiscountPercent()) {
                        }
                        $totalDiscountAmount += $child->getDiscountAmount();//*$item->getQty();
                        $baseTotalDiscountAmount += $child->getBaseDiscountAmount();//*$item->getQty();

                        $child->setRowTotalWithDiscount($child->getRowTotal() - $child->getDiscountAmount());
                        $child->setBaseRowTotalWithDiscount($child->getBaseRowTotal() - $child->getBaseDiscountAmount());

                        $subtotalWithDiscount += $child->getRowTotalWithDiscount();
                        $baseSubtotalWithDiscount += $child->getBaseRowTotalWithDiscount();
                    }
                } else {
                    $eventArgs['item'] = $item;
                    Mage::dispatchEvent('sales_quote_address_discount_item', $eventArgs);

                    if ($item->getDiscountAmount() || $item->getFreeShipping()) {
                        $hasDiscount = true;
                    }
                    $totalDiscountAmount += $item->getDiscountAmount();
                    $baseTotalDiscountAmount += $item->getBaseDiscountAmount();

                    $item->setRowTotalWithDiscount($item->getRowTotal() - $item->getDiscountAmount());
                    $item->setBaseRowTotalWithDiscount($item->getBaseRowTotal() - $item->getBaseDiscountAmount());

                    $subtotalWithDiscount += $item->getRowTotalWithDiscount();
                    $baseSubtotalWithDiscount += $item->getBaseRowTotalWithDiscount();
                }
            }
        }
        $address->setDiscountAmount($totalDiscountAmount);
        $address->setSubtotalWithDiscount($subtotalWithDiscount);
        $address->setBaseDiscountAmount($baseTotalDiscountAmount);
        $address->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);

        $address->setGrandTotal($address->getGrandTotal() - $address->getDiscountAmount());
        $address->setBaseGrandTotal($address->getBaseGrandTotal() - $address->getBaseDiscountAmount());
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $address
     * @return $this
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $amount = $address->getDiscountAmount();
        if ($amount != 0) {
            $title = Mage::helper('sales')->__('Discount');
            $code = $address->getCouponCode();
            if (strlen($code)) {
                $title = Mage::helper('sales')->__('Discount (%s)', $code);
            }
            $address->addTotal([
                'code' => $this->getCode(),
                'title' => $title,
                'value' => -$amount
            ]);
        }
        return $this;
    }
}
