<?php

/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog Custom Category design Model
 *
 * @category   Mage
 * @package    Mage_Catalog
 */
class Mage_Catalog_Model_Design extends Mage_Core_Model_Abstract
{
    public const APPLY_FOR_PRODUCT     = 1;
    public const APPLY_FOR_CATEGORY    = 2;

    /**
     * @deprecated after 1.4.1.0
     * Category / Custom Design / Apply To constants
     */
    public const CATEGORY_APPLY_CATEGORY_AND_PRODUCT_RECURSIVE = 1;
    public const CATEGORY_APPLY_CATEGORY_ONLY                  = 2;
    public const CATEGORY_APPLY_CATEGORY_AND_PRODUCT_ONLY      = 3;
    public const CATEGORY_APPLY_CATEGORY_RECURSIVE             = 4;

    /**
     * Apply design from catalog object
     *
     * @deprecated after 1.4.2.0-beta1
     *
     * @param array|Mage_Catalog_Model_Category|Mage_Catalog_Model_Product $object
     * @param int $calledFrom
     * @return $this
     */
    public function applyDesign($object, $calledFrom = 0)
    {
        if ($calledFrom != self::APPLY_FOR_CATEGORY && $calledFrom != self::APPLY_FOR_PRODUCT) {
            return $this;
        }

        // If Flat Data enabled then use it but only on frontend
        if (Mage::helper('catalog/category_flat')->isAvailable() && !Mage::app()->getStore()->isAdmin()) {
            $this->_applyDesign($object, $calledFrom);
        } else {
            $this->_inheritDesign($object, $calledFrom);
        }

        return $this;
    }

    /**
     * Apply package and theme
     *
     * @param string $package
     * @param string $theme
     */
    protected function _apply($package, $theme)
    {
        Mage::getSingleton('core/design_package')
            ->setPackageName($package)
            ->setTheme($theme);
    }

    /**
     * Apply custom design
     *
     * @param string $design
     * @return void|false
     */
    public function applyCustomDesign($design)
    {
        $designInfo = explode('/', $design);
        if (count($designInfo) != 2) {
            return false;
        }
        $package = $designInfo[0];
        $theme   = $designInfo[1];
        $this->_apply($package, $theme);
    }

    /**
     * Check is allow apply for
     *
     * @deprecated after 1.4.1.0
     *
     * @param int $applyForObject
     * @param int $applyTo
     * @param int $pass
     * @return bool
     */
    protected function _isApplyFor($applyForObject, $applyTo, $pass = 0)
    {
        $hasError = false;

        if ($pass == 0) {
            switch ($applyForObject) {
                case self::APPLY_FOR_CATEGORY:
                    break;
                case self::APPLY_FOR_PRODUCT:
                    $validApplyTo = [
                        self::CATEGORY_APPLY_CATEGORY_AND_PRODUCT_RECURSIVE,
                        self::CATEGORY_APPLY_CATEGORY_AND_PRODUCT_ONLY,
                    ];
                    if ($applyTo && !in_array($applyTo, $validApplyTo)) {
                        $hasError = true;
                    }
                    break;
                default:
                    $hasError = true;
                    break;
            }
        } else {
            switch ($applyForObject) {
                case self::APPLY_FOR_CATEGORY:
                    $validApplyTo = [
                        self::CATEGORY_APPLY_CATEGORY_AND_PRODUCT_RECURSIVE,
                        self::CATEGORY_APPLY_CATEGORY_RECURSIVE,
                    ];
                    if ($applyTo && !in_array($applyTo, $validApplyTo)) {
                        $hasError = true;
                    }
                    break;
                case self::APPLY_FOR_PRODUCT:
                    $validApplyTo = [
                        self::CATEGORY_APPLY_CATEGORY_AND_PRODUCT_RECURSIVE,
                    ];
                    if ($applyTo && !in_array($applyTo, $validApplyTo)) {
                        $hasError = true;
                    }
                    break;
                default:
                    $hasError = true;
                    break;
            }
        }

        return !$hasError;
    }

    /**
     * Check and apply design
     *
     * @param string $design
     * @return bool
     * @deprecated after 1.4.2.0-beta1
     *
     */
    protected function _isApplyDesign($design, array $date)
    {
        if (!array_key_exists('from', $date) || !array_key_exists('to', $date)) {
            return false;
        }

        $designInfo = explode('/', $design);
        if (count($designInfo) != 2) {
            return false;
        }

        // define package and theme
        $package    = $designInfo[0];
        $theme      = $designInfo[1];

        // compare dates
        if (Mage::app()->getLocale()->isStoreDateInInterval(null, $date['from'], $date['to'])) {
            $this->_apply($package, $theme);
            return true;
        }

        return false;
    }

    /**
     * Recursively apply design
     *
     * @deprecated after 1.4.2.0-beta1
     *
     * @param Varien_Object $object
     * @param int $calledFrom
     *
     * @return $this
     */
    protected function _inheritDesign($object, $calledFrom = 0)
    {
        $useParentSettings = false;
        if ($object instanceof Mage_Catalog_Model_Product) {
            $category = $object->getCategory();

            if ($category && $category->getId()) {
                return $this->_inheritDesign($category, $calledFrom);
            }
        } elseif ($object instanceof Mage_Catalog_Model_Category) {
            $category = $object->getParentCategory();

            $useParentSettings = $object->getCustomUseParentSettings();
            if ($useParentSettings) {
                if ($category &&
                    $category->getId() &&
                    $category->getLevel() > 1 &&
                    $category->getId() != Mage_Catalog_Model_Category::TREE_ROOT_ID
                ) {
                    return $this->_inheritDesign($category, $calledFrom);
                }
            }

            if ($calledFrom == self::APPLY_FOR_PRODUCT) {
                $applyToProducts = $object->getCustomApplyToProducts();
                if (!$applyToProducts) {
                    return $this;
                }
            }
        }

        if (!$useParentSettings) {
            $design = $object->getCustomDesign();
            $date   = $object->getCustomDesignDate();
            $this->_isApplyDesign($design, $date);
        }

        return $this;
    }

    /**
     * Apply design recursively (if using EAV)
     *
     * @deprecated after 1.4.1.0
     *
     * @param Mage_Catalog_Model_Category|Mage_Catalog_Model_Product $object
     * @param int $calledFrom
     * @param int $pass
     *
     * @return $this
     */
    protected function _applyDesignRecursively($object, $calledFrom = 0, $pass = 0)
    {
        $design  = $object->getCustomDesign();
        $date    = $object->getCustomDesignDate();
        $applyTo = $object->getCustomDesignApply();

        $checkAndApply = $this->_isApplyFor($calledFrom, $applyTo, $pass)
            && $this->_isApplyDesign($design, $date);
        if ($checkAndApply) {
            return $this;
        }

        $pass++;

        $category = null;
        if ($object instanceof Mage_Catalog_Model_Product) {
            $category = $object->getCategory();
            $pass--;
        } elseif ($object instanceof Mage_Catalog_Model_Category) {
            $category = $object->getParentCategory();
        }

        if ($category && $category->getId()) {
            $this->_applyDesignRecursively($category, $calledFrom, $pass);
        }

        return $this;
    }

    /**
     * @param mixed $designUpdateData
     * @param int $calledFrom
     * @param bool $loaded
     * @param int $pass
     * @return Mage_Catalog_Model_Design
     * @deprecated after 1.4.2.0-beta1
     */
    protected function _applyDesign($designUpdateData, $calledFrom = 0, $loaded = false, $pass = 0)
    {
        $objects = [];
        if (is_object($designUpdateData)) {
            $objects = [$designUpdateData];
        } elseif (is_array($designUpdateData)) {
            $objects = &$designUpdateData;
        }
        foreach ($objects as $object) {
            $design  = $object->getCustomDesign();
            $date    = $object->getCustomDesignDate();
            $applyTo = $object->getCustomDesignApply();

            $checkAndApply = $this->_isApplyFor($calledFrom, $applyTo, $pass)
                && $this->_isApplyDesign($design, $date);
            if ($checkAndApply) {
                return $this;
            }
        }

        $pass++;

        if ($loaded === false && is_object($designUpdateData)) {
            $_designUpdateData = [];
            if ($designUpdateData instanceof Mage_Catalog_Model_Product) {
                $_category = $designUpdateData->getCategory();
                $_designUpdateData = array_merge(
                    $_designUpdateData,
                    [$_category],
                );
                $pass--;
            } elseif ($designUpdateData instanceof Mage_Catalog_Model_Category) {
                $_category = &$designUpdateData;
            }
            if (isset($_category) && $_category->getId()) {
                $_designUpdateData = array_merge(
                    $_designUpdateData,
                    $_category->getResource()->getDesignUpdateData($_category),
                );
                $this->_applyDesign($_designUpdateData, $calledFrom, true, $pass);
            }
        }
        return $this;
    }

    /**
     * Get custom layout settings
     *
     * @param Mage_Catalog_Model_Category|Mage_Catalog_Model_Product $object
     * @return Varien_Object
     */
    public function getDesignSettings($object)
    {
        if ($object instanceof Mage_Catalog_Model_Product) {
            $currentCategory = $object->getCategory();
        } else {
            $currentCategory = $object;
        }

        $category = null;
        if ($currentCategory) {
            $category = $currentCategory->getParentDesignCategory($currentCategory);
        }

        if ($object instanceof Mage_Catalog_Model_Product) {
            if ($category && $category->getCustomApplyToProducts()) {
                return $this->_mergeSettings($this->_extractSettings($category), $this->_extractSettings($object));
            } else {
                return $this->_extractSettings($object);
            }
        } else {
            return $this->_extractSettings($category);
        }
    }

    /**
     * Extract custom layout settings from category or product object
     *
     * @param Mage_Catalog_Model_Category|Mage_Catalog_Model_Product $object
     * @return Varien_Object
     */
    protected function _extractSettings($object)
    {
        $settings = new Varien_Object();
        if (!$object) {
            return $settings;
        }
        $date = $object->getCustomDesignDate();
        if (array_key_exists('from', $date) && array_key_exists('to', $date)
            && Mage::app()->getLocale()->isStoreDateInInterval(null, $date['from'], $date['to'])
        ) {
            $customLayout = $object->getCustomLayoutUpdate();
            if ($customLayout) {
                try {
                    if (!Mage::getModel('core/layout_validator')->isValid($customLayout)) {
                        $customLayout = '';
                    }
                } catch (Exception $e) {
                    $customLayout = '';
                }
            }
            $settings->setCustomDesign($object->getCustomDesign())
                ->setPageLayout($object->getPageLayout())
                ->setLayoutUpdates((array) $customLayout);
        }
        return $settings;
    }

    /**
     * Merge custom design settings
     *
     * @param Varien_Object $categorySettings
     * @param Varien_Object $productSettings
     * @return Varien_Object
     */
    protected function _mergeSettings($categorySettings, $productSettings)
    {
        if ($productSettings->getCustomDesign()) {
            $categorySettings->setCustomDesign($productSettings->getCustomDesign());
        }
        if ($productSettings->getPageLayout()) {
            $categorySettings->setPageLayout($productSettings->getPageLayout());
        }
        if ($productSettings->getLayoutUpdates()) {
            $update = array_merge($categorySettings->getLayoutUpdates(), $productSettings->getLayoutUpdates());
            $categorySettings->setLayoutUpdates($update);
        }
        return $categorySettings;
    }
}
