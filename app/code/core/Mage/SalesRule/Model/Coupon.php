<?php
/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * SalesRule Coupon Model
 *
 * @category   Mage
 * @package    Mage_SalesRule
 *
 * @method Mage_SalesRule_Model_Resource_Coupon _getResource()
 * @method Mage_SalesRule_Model_Resource_Coupon getResource()
 * @method Mage_SalesRule_Model_Resource_Coupon_Collection getCollection()
 *
 * @method int getRuleId()
 * @method $this setRuleId(int $value)
 * @method string getCode()
 * @method $this setCode(string $value)
 * @method int getUsageLimit()
 * @method $this setUsageLimit(int $value)
 * @method int getUsagePerCustomer()
 * @method $this setUsagePerCustomer(int $value)
 * @method int getTimesUsed()
 * @method $this setTimesUsed(int $value)
 * @method Zend_Date getExpirationDate()
 * @method $this setExpirationDate(Zend_Date $value)
 * @method int getIsPrimary()
 * @method $this setIsPrimary(int $value)
 * @method int getType()
 * @method $this setType(int $value)
 */
class Mage_SalesRule_Model_Coupon extends Mage_Core_Model_Abstract
{
    /**
     * Coupon's owner rule instance
     *
     * @var Mage_SalesRule_Model_Rule
     */
    protected $_rule;

    protected function _construct()
    {
        parent::_construct();
        $this->_init('salesrule/coupon');
    }

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        if (!$this->getRuleId() && $this->_rule instanceof Mage_SalesRule_Model_Rule) {
            $this->setRuleId($this->_rule->getId());
        }
        return parent::_beforeSave();
    }

    /**
     * Set rule instance
     *
     * @param  Mage_SalesRule_Model_Rule $rule
     * @return $this
     */
    public function setRule(Mage_SalesRule_Model_Rule $rule)
    {
        $this->_rule = $rule;
        return $this;
    }

    /**
     * Load primary coupon for specified rule
     *
     * @param Mage_SalesRule_Model_Rule|int $rule
     * @return $this
     */
    public function loadPrimaryByRule($rule)
    {
        $this->getResource()->loadPrimaryByRule($this, $rule);
        return $this;
    }

    /**
     * Load Shopping Cart Price Rule by coupon code
     *
     * @param string $couponCode
     * @return $this
     */
    public function loadByCode($couponCode)
    {
        $this->load($couponCode, 'code');
        return $this;
    }
}
