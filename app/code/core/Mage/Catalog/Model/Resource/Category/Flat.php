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
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Category flat model
 *
 * @category   Mage
 * @package    Mage_Catalog
 */
class Mage_Catalog_Model_Resource_Category_Flat extends Mage_Index_Model_Resource_Abstract
{
    /**
     * Amount of categories to be processed in batch
     */
    public const CATEGORY_BATCH = 500;

    /**
     * Store id
     *
     * @var int|null
     */
    protected $_storeId = null;

    /**
     * Loaded
     *
     * @var bool
     */
    protected $_loaded = false;

    /**
     * Nodes
     *
     * @var array
     */
    protected $_nodes = [];

    /**
     * Columns
     *
     * @var array
     */
    protected $_columns = null;

    /**
     * Columns sql
     *
     * @var array
     */
    protected $_columnsSql = null;

    /**
     * Attribute codes
     *
     * @var array
     */
    protected $_attributeCodes = null;

    /**
     * Inactive categories ids
     *
     * @var array
     */
    protected $_inactiveCategoryIds = null;

    /**
     * Store flag which defines if Catalog Category Flat Data has been initialized
     *
     * @var array
     */
    protected $_isBuilt = [];

    /**
     * Store flag which defines if Catalog Category Flat Data has been initialized
     *
     * @deprecated after 1.7.0.0 use $this->_isBuilt instead
     *
     * @var bool|null
     */
    protected $_isRebuilt = null;

    /**
     * array with root category id per store
     *
     * @var array|null
     */
    protected $_storesRootCategories;

    /**
     * Whether table changes are allowed
     *
     * @var bool
     */
    protected $_allowTableChanges = true;

    /**
     * Factory instance
     *
     * @var Mage_Catalog_Model_Factory
     */
    protected $_factory;

    /**
     * Initialize factory instance
     */
    public function __construct(array $args = [])
    {
        $this->_factory = !empty($args['factory']) ? $args['factory'] : Mage::getSingleton('catalog/factory');
        parent::__construct();
    }

    protected function _construct()
    {
        $this->_init('catalog/category_flat', 'entity_id');
    }

    /**
     * Set store id
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = (int) $storeId;
        return $this;
    }

    /**
     * Return store id
     *
     * @return int
     */
    public function getStoreId()
    {
        if (is_null($this->_storeId)) {
            return (int) Mage::app()->getStore()->getId();
        }
        return $this->_storeId;
    }

    /**
     * Get main table name
     *
     * @return string
     */
    public function getMainTable()
    {
        return $this->getMainStoreTable($this->getStoreId());
    }

    /**
     * Return name of table for given $storeId.
     *
     * @param int $storeId
     * @return string
     */
    public function getMainStoreTable($storeId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
    {
        if (is_string($storeId)) {
            $storeId = (int) $storeId;
        }
        if ($this->getUseStoreTables() && $storeId) {
            $suffix = sprintf('store_%d', $storeId);
            $table = $this->getTable(['catalog/category_flat', $suffix]);
        } else {
            $table = parent::getMainTable();
        }

        return $table;
    }

    /**
     * Return true if need use for each store different table of flat categories data.
     *
     * @return bool
     */
    public function getUseStoreTables()
    {
        return true;
    }

    /**
     * Add inactive categories ids
     *
     * @param array $ids
     * @return $this
     */
    public function addInactiveCategoryIds($ids)
    {
        if (!is_array($this->_inactiveCategoryIds)) {
            $this->_initInactiveCategoryIds();
        }
        $this->_inactiveCategoryIds = array_merge($ids, $this->_inactiveCategoryIds);
        return $this;
    }

    /**
     * Retrieve inactive categories ids
     *
     * @return $this
     */
    protected function _initInactiveCategoryIds()
    {
        $this->_inactiveCategoryIds = [];
        Mage::dispatchEvent('catalog_category_tree_init_inactive_category_ids', ['tree' => $this]);
        return $this;
    }

    /**
     * Retrieve inactive categories ids
     *
     * @return array
     */
    public function getInactiveCategoryIds()
    {
        if (!is_array($this->_inactiveCategoryIds)) {
            $this->_initInactiveCategoryIds();
        }

        return $this->_inactiveCategoryIds;
    }

    /**
     * Load nodes by parent id
     *
     * @param Mage_Catalog_Model_Category|int $parentNode
     * @param int $recursionLevel
     * @param int $storeId
     * @param bool $onlyActive
     * @return array
     */
    protected function _loadNodes($parentNode = null, $recursionLevel = 0, $storeId = 0, $onlyActive = true)
    {
        $_conn = $this->_getReadAdapter();
        $startLevel = 1;
        $parentPath = '';
        if ($parentNode instanceof Mage_Catalog_Model_Category) {
            $parentPath = $parentNode->getPath();
            $startLevel = $parentNode->getLevel();
        } elseif (is_numeric($parentNode)) {
            $selectParent = $_conn->select()
                ->from($this->getMainStoreTable($storeId))
                ->where('entity_id = ?', $parentNode)
                ->where('store_id = ?', $storeId);
            $parentNode = $_conn->fetchRow($selectParent);
            if ($parentNode) {
                $parentPath = $parentNode['path'];
                $startLevel = $parentNode['level'];
            }
        }
        $select = $_conn->select()
            ->from(
                ['main_table' => $this->getMainStoreTable($storeId)],
                ['entity_id',
                    new Zend_Db_Expr('main_table.' . $_conn->quoteIdentifier('name')),
                    new Zend_Db_Expr('main_table.' . $_conn->quoteIdentifier('path')),
                    'is_active',
                    'is_anchor'],
            )

            ->where('main_table.include_in_menu = ?', '1')
            ->order('main_table.position');

        if ($onlyActive) {
            $select->where('main_table.is_active = ?', '1');
        }

        /** @var Mage_Catalog_Helper_Category_Url_Rewrite_Interface $urlRewrite */
        $urlRewrite = $this->_factory->getCategoryUrlRewriteHelper();
        $urlRewrite->joinTableToSelect($select, $storeId);

        if ($parentPath) {
            $select->where($_conn->quoteInto('main_table.path like ?', "$parentPath/%"));
        }
        if ($recursionLevel != 0) {
            $levelField = $_conn->quoteIdentifier('level');
            $select->where($levelField . ' <= ?', $startLevel + $recursionLevel);
        }

        $inactiveCategories = $this->getInactiveCategoryIds();

        if (!empty($inactiveCategories)) {
            $select->where('main_table.entity_id NOT IN (?)', $inactiveCategories);
        }

        // Allow extensions to modify select (e.g. add custom category attributes to select)
        Mage::dispatchEvent('catalog_category_flat_loadnodes_before', ['select' => $select]);

        $arrNodes = $_conn->fetchAll($select);
        $nodes = [];
        foreach ($arrNodes as $node) {
            $node['id'] = $node['entity_id'];
            $nodes[$node['id']] = Mage::getModel('catalog/category')->setData($node);
        }

        return $nodes;
    }

    /**
     * Creating sorted array of nodes
     *
     * @param array $children
     * @param string $path
     * @param Varien_Object $parent
     */
    public function addChildNodes($children, $path, $parent)
    {
        if (isset($children[$path])) {
            foreach ($children[$path] as $child) {
                $childrenNodes = $parent->getChildrenNodes();
                if ($childrenNodes && isset($childrenNodes[$child->getId()])) {
                    $childrenNodes[$child['entity_id']]->setChildrenNodes([$child->getId() => $child]);
                } else {
                    if ($childrenNodes) {
                        $childrenNodes[$child->getId()] = $child;
                    } else {
                        $childrenNodes = [$child->getId() => $child];
                    }
                    $parent->setChildrenNodes($childrenNodes);
                }

                if ($path) {
                    $childrenPath = explode('/', $path);
                } else {
                    $childrenPath = [];
                }
                $childrenPath[] = $child->getId();
                $childrenPath = implode('/', $childrenPath);
                $this->addChildNodes($children, $childrenPath, $child);
            }
        }
    }

    /**
     * Return sorted array of nodes
     *
     * @param int|null $parentId
     * @param int $recursionLevel
     * @param int $storeId
     * @return array
     */
    public function getNodes($parentId, $recursionLevel = 0, $storeId = 0)
    {
        if (!$this->_loaded) {
            $selectParent = $this->_getReadAdapter()->select()
                ->from($this->getMainStoreTable($storeId))
                ->where('entity_id = ?', $parentId);
            if ($parentNode = $this->_getReadAdapter()->fetchRow($selectParent)) {
                $parentNode['id'] = $parentNode['entity_id'];
                $parentNode = Mage::getModel('catalog/category')->setData($parentNode);
                $this->_nodes[$parentNode->getId()] = $parentNode;
                $nodes = $this->_loadNodes($parentNode, $recursionLevel, $storeId);
                $childrenItems = [];
                foreach ($nodes as $node) {
                    $pathToParent = explode('/', $node->getPath());
                    array_pop($pathToParent);
                    $pathToParent = implode('/', $pathToParent);
                    $childrenItems[$pathToParent][] = $node;
                }
                $this->addChildNodes($childrenItems, $parentNode->getPath(), $parentNode);
                $childrenNodes = $this->_nodes[$parentNode->getId()];
                if ($childrenNodes->getChildrenNodes()) {
                    $this->_nodes = $childrenNodes->getChildrenNodes();
                } else {
                    $this->_nodes = [];
                }
                $this->_loaded = true;
            }
        }
        return $this->_nodes;
    }

    /**
     * Return array or collection of categories
     *
     * @param int $parent
     * @param int $recursionLevel
     * @param bool|string $sorted
     * @param bool $asCollection
     * @param bool $toLoad
     * @return array|Varien_Data_Collection
     */
    public function getCategories($parent, $recursionLevel = 0, $sorted = false, $asCollection = false, $toLoad = true)
    {
        if ($asCollection) {
            $select = $this->_getReadAdapter()->select()
                ->from(['mt' => $this->getMainStoreTable($this->getStoreId())], ['path'])
                ->where('mt.entity_id = ?', $parent);
            $parentPath = $this->_getReadAdapter()->fetchOne($select);

            $collection = Mage::getModel('catalog/category')->getCollection()
                ->addNameToResult()
                ->addUrlRewriteToResult()
                ->addParentPathFilter($parentPath)
                ->addStoreFilter()
                ->addIsActiveFilter()
                ->addAttributeToFilter('include_in_menu', 1)
                ->addSortedField($sorted);
            if ($toLoad) {
                return $collection->load();
            }
            return $collection;
        }
        return $this->getNodes($parent, $recursionLevel, Mage::app()->getStore()->getId());
    }

    /**
     * Return node with id $nodeId
     *
     * @param int $nodeId
     * @param array $nodes
     * @return Varien_Object|array
     */
    public function getNodeById($nodeId, $nodes = null)
    {
        if (is_null($nodes)) {
            $nodes = $this->getNodes($nodeId);
        }
        if (isset($nodes[$nodeId])) {
            return $nodes[$nodeId];
        }
        foreach ($nodes as $node) {
            if ($node->getChildrenNodes()) {
                return $this->getNodeById($nodeId, $node->getChildrenNodes());
            }
        }
        return [];
    }

    /**
     * Check if Catalog Category Flat Data has been initialized
     *
     * @param bool|int|\Mage_Core_Model_Store|null $storeView Store(id) for which the value is checked
     * @return bool
     */
    public function isBuilt($storeView = null)
    {
        $storeView = is_null($storeView) ? Mage::app()->getDefaultStoreView() : Mage::app()->getStore($storeView);
        if ($storeView === null) {
            $storeId = Mage_Core_Model_App::ADMIN_STORE_ID;
        } else {
            $storeId = $storeView->getId();
        }
        if (!isset($this->_isBuilt[$storeId])) {
            $select = $this->_getReadAdapter()->select()
                ->from($this->getMainStoreTable($storeId), 'entity_id')
                ->limit(1);
            try {
                $this->_isBuilt[$storeId] = (bool) $this->_getReadAdapter()->fetchOne($select);
            } catch (Exception $e) {
                $this->_isBuilt[$storeId] = false;
            }
        }
        return $this->_isBuilt[$storeId];
    }

    /**
     * Rebuild flat data from eav
     *
     * @param array|null $stores
     * @return $this
     */
    public function rebuild($stores = null)
    {
        if ($stores === null) {
            $stores = Mage::app()->getStores();
        }

        if (!is_array($stores)) {
            $stores = [$stores];
        }

        $rootId = Mage_Catalog_Model_Category::TREE_ROOT_ID;
        $categories = [];
        $categoriesIds = [];
        /** @var Mage_Core_Model_Store $store */
        foreach ($stores as $store) {
            if ($this->_allowTableChanges) {
                $this->_createTable($store->getId());
            }

            if (!isset($categories[$store->getRootCategoryId()])) {
                $select = $this->_getWriteAdapter()->select()
                    ->from($this->getTable('catalog/category'))
                    ->where('path = ?', (string) $rootId)
                    ->orWhere('path = ?', "{$rootId}/{$store->getRootCategoryId()}")
                    ->orWhere('path LIKE ?', "{$rootId}/{$store->getRootCategoryId()}/%");
                $categories[$store->getRootCategoryId()] = $this->_getWriteAdapter()->fetchAll($select);
                $categoriesIds[$store->getRootCategoryId()] = [];
                foreach ($categories[$store->getRootCategoryId()] as $category) {
                    $categoriesIds[$store->getRootCategoryId()][] = $category['entity_id'];
                }
            }
            $categoriesIdsChunks = array_chunk($categoriesIds[$store->getRootCategoryId()], self::CATEGORY_BATCH);
            foreach ($categoriesIdsChunks as $categoriesIdsChunk) {
                $attributesData = $this->_getAttributeValues($categoriesIdsChunk, $store->getId());
                $data = [];
                foreach ($categories[$store->getRootCategoryId()] as $category) {
                    if (!isset($attributesData[$category['entity_id']])) {
                        continue;
                    }
                    $category['store_id'] = $store->getId();
                    $data[] = $this->_prepareValuesToInsert(
                        array_merge($category, $attributesData[$category['entity_id']]),
                    );
                }
                $this->_getWriteAdapter()->insertMultiple($this->getMainStoreTable($store->getId()), $data);
            }
        }
        return $this;
    }

    /**
     * Prepare array of column and columnValue pairs
     *
     * @param array $data
     * @return array
     */
    protected function _prepareValuesToInsert($data)
    {
        $values = [];
        foreach (array_keys($this->_columns) as $key => $column) {
            if (isset($data[$column])) {
                $values[$column] = $data[$column];
            } else {
                $values[$column] = null;
            }
        }
        return $values;
    }

    /**
     * Create Flat Table(s)
     *
     * @param array|int $stores
     * @return $this
     */
    public function createTable($stores)
    {
        return $this->_createTable($stores);
    }

    /**
     * Creating table and adding attributes as fields to table
     *
     * @param array|int $store
     * @return $this
     */
    protected function _createTable($store)
    {
        $tableName = $this->getMainStoreTable($store);
        $_writeAdapter = $this->_getWriteAdapter();
        $_writeAdapter->dropTable($tableName);
        $table = $this->_getWriteAdapter()
            ->newTable($tableName)
            ->setComment(sprintf('Catalog Category Flat (Store %d)', $store));

        //Adding columns
        if ($this->_columnsSql === null) {
            $this->_columns = array_merge($this->_getStaticColumns(), $this->_getEavColumns());
            foreach ($this->_columns as $fieldName => $fieldProp) {
                $default = $fieldProp['default'];
                if ($fieldProp['type'][0] == Varien_Db_Ddl_Table::TYPE_TIMESTAMP
                    && $default === 'CURRENT_TIMESTAMP'
                ) {
                    $default = Varien_Db_Ddl_Table::TIMESTAMP_INIT;
                }
                $table->addColumn($fieldName, $fieldProp['type'][0], $fieldProp['type'][1], [
                    'nullable' => $fieldProp['nullable'],
                    'unsigned' => $fieldProp['unsigned'],
                    'default'  => $default,
                    'primary'  => $fieldProp['primary'] ?? false,
                ], ($fieldProp['comment'] != '') ?
                    $fieldProp['comment'] :
                    ucwords(str_replace('_', ' ', $fieldName)));
            }
        }

        // Adding indexes
        $table->addIndex(
            $_writeAdapter->getIndexName($tableName, ['entity_id']),
            ['entity_id'],
            ['type' => 'primary'],
        );
        $table->addIndex(
            $_writeAdapter->getIndexName($tableName, ['store_id']),
            ['store_id'],
            ['type' => 'index'],
        );
        $table->addIndex(
            $_writeAdapter->getIndexName($tableName, ['path']),
            ['path'],
            ['type' => 'index'],
        );
        $table->addIndex(
            $_writeAdapter->getIndexName($tableName, ['level']),
            ['level'],
            ['type' => 'index'],
        );

        // Adding foreign keys
        $table->addForeignKey(
            $_writeAdapter->getForeignKeyName(
                $tableName,
                'entity_id',
                $this->getTable('catalog/category'),
                'entity_id',
            ),
            'entity_id',
            $this->getTable('catalog/category'),
            'entity_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE,
        );
        $table->addForeignKey(
            $_writeAdapter->getForeignKeyName($tableName, 'store_id', $this->getTable('core/store'), 'store_id'),
            'store_id',
            $this->getTable('core/store'),
            'store_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE,
            Varien_Db_Ddl_Table::ACTION_CASCADE,
        );
        $_writeAdapter->createTable($table);
        return $this;
    }

    /**
     * Return array of static columns
     *
     * @return array
     */
    protected function _getStaticColumns()
    {
        /** @var Mage_Eav_Model_Resource_Helper_Mysql4 $helper */
        $helper = Mage::getResourceHelper('catalog');
        $columns = [];
        $columnsToSkip = ['entity_type_id', 'attribute_set_id'];
        $describe = $this->_getReadAdapter()->describeTable($this->getTable('catalog/category'));

        foreach ($describe as $column) {
            if (in_array($column['COLUMN_NAME'], $columnsToSkip)) {
                continue;
            }
            $isUnsigned = '';
            $ddlType = $helper->getDdlTypeByColumnType($column['DATA_TYPE']);
            $column['DEFAULT'] = empty($column['DEFAULT']) ? $column['DEFAULT'] : trim($column['DEFAULT'], "' ");
            switch ($ddlType) {
                case Varien_Db_Ddl_Table::TYPE_SMALLINT:
                case Varien_Db_Ddl_Table::TYPE_INTEGER:
                case Varien_Db_Ddl_Table::TYPE_BIGINT:
                    $isUnsigned = (bool) $column['UNSIGNED'];
                    if ($column['DEFAULT'] === '') {
                        $column['DEFAULT'] = null;
                    }

                    $options = null;
                    if ($column['SCALE'] > 0) {
                        $ddlType = Varien_Db_Ddl_Table::TYPE_DECIMAL;
                    } else {
                        break;
                    }
                    // no break
                case Varien_Db_Ddl_Table::TYPE_DECIMAL:
                    $options = $column['PRECISION'] . ',' . $column['SCALE'];
                    $isUnsigned = null;
                    if ($column['DEFAULT'] === '') {
                        $column['DEFAULT'] = null;
                    }
                    break;
                case Varien_Db_Ddl_Table::TYPE_TEXT:
                    $options = $column['LENGTH'];
                    $isUnsigned = null;
                    break;
                case Varien_Db_Ddl_Table::TYPE_TIMESTAMP:
                    $options = null;
                    $isUnsigned = null;
                    break;
                case Varien_Db_Ddl_Table::TYPE_DATETIME:
                    $isUnsigned = null;
                    break;
            }
            $columns[$column['COLUMN_NAME']] = [
                'type' => [$ddlType, $options],
                'unsigned' => $isUnsigned,
                'nullable' => $column['NULLABLE'],
                'default' => $column['DEFAULT'] ?? false,
                'comment' => $column['COLUMN_NAME'],
            ];
        }
        $columns['store_id'] = [
            'type' => [Varien_Db_Ddl_Table::TYPE_SMALLINT, 5],
            'unsigned' => true,
            'nullable' => false,
            'default' => '0',
            'comment' => 'Store Id',
        ];
        return $columns;
    }

    /**
     * Return array of eav columns, skip attribute with static type
     *
     * @return array
     */
    protected function _getEavColumns()
    {
        $columns = [];
        $attributes = $this->_getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute['backend_type'] === 'static') {
                continue;
            }
            $columns[$attribute['attribute_code']] = [];
            switch ($attribute['backend_type']) {
                case 'varchar':
                    $columns[$attribute['attribute_code']] = [
                        'type' => [Varien_Db_Ddl_Table::TYPE_TEXT, 255],
                        'unsigned' => null,
                        'nullable' => true,
                        'default' => null,
                        'comment' => (string) $attribute['frontend_label'],
                    ];
                    break;
                case 'int':
                    $columns[$attribute['attribute_code']] = [
                        'type' => [Varien_Db_Ddl_Table::TYPE_INTEGER, null],
                        'unsigned' => null,
                        'nullable' => true,
                        'default' => null,
                        'comment' => (string) $attribute['frontend_label'],
                    ];
                    break;
                case 'text':
                    $columns[$attribute['attribute_code']] = [
                        'type' => [Varien_Db_Ddl_Table::TYPE_TEXT, '64k'],
                        'unsigned' => null,
                        'nullable' => true,
                        'default' => null,
                        'comment' => (string) $attribute['frontend_label'],
                    ];
                    break;
                case 'datetime':
                    $columns[$attribute['attribute_code']] = [
                        'type' => [Varien_Db_Ddl_Table::TYPE_DATETIME, null],
                        'unsigned' => null,
                        'nullable' => true,
                        'default' => null,
                        'comment' => (string) $attribute['frontend_label'],
                    ];
                    break;
                case 'decimal':
                    $columns[$attribute['attribute_code']] = [
                        'type' => [Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4'],
                        'unsigned' => null,
                        'nullable' => true,
                        'default' => null,
                        'comment' => (string) $attribute['frontend_label'],
                    ];
                    break;
            }
        }
        return $columns;
    }

    /**
     * Return array of attribute codes for entity type 'catalog_category'
     *
     * @return array
     */
    protected function _getAttributes()
    {
        if ($this->_attributeCodes === null) {
            $select = $this->_getWriteAdapter()->select()
                ->from($this->getTable('eav/entity_type'), [])
                ->join(
                    $this->getTable('eav/attribute'),
                    $this->getTable('eav/attribute')
                        . '.entity_type_id = ' . $this->getTable('eav/entity_type') . '.entity_type_id',
                    $this->getTable('eav/attribute') . '.*',
                )
                ->where(
                    $this->getTable('eav/entity_type') . '.entity_type_code = ?',
                    Mage_Catalog_Model_Category::ENTITY,
                );
            $this->_attributeCodes = [];
            foreach ($this->_getWriteAdapter()->fetchAll($select) as $attribute) {
                $this->_attributeCodes[$attribute['attribute_id']] = $attribute;
            }
        }
        return $this->_attributeCodes;
    }

    /**
     * Return attribute values for given entities and store
     *
     * @param int|string|array $entityIds
     * @param int $storeId
     * @return array
     */
    protected function _getAttributeValues($entityIds, $storeId)
    {
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        $values = [];

        foreach ($entityIds as $entityId) {
            $values[$entityId] = [];
        }
        $attributes = $this->_getAttributes();
        $attributesType = [
            'varchar',
            'int',
            'decimal',
            'text',
            'datetime',
        ];
        foreach ($attributesType as $type) {
            foreach ($this->_getAttributeTypeValues($type, $entityIds, $storeId) as $row) {
                if (isset($attributes[$row['attribute_id']])) {
                    $values[$row['entity_id']][$attributes[$row['attribute_id']]['attribute_code']] = $row['value'];
                }
            }
        }
        return $values;
    }

    /**
     * Return attribute values for given entities and store of specific attribute type
     *
     * @param string $type
     * @param array $entityIds
     * @param int $storeId
     * @return array
     */
    protected function _getAttributeTypeValues($type, $entityIds, $storeId)
    {
        $select = $this->_getWriteAdapter()->select()
            ->from(
                ['def' => $this->getTable(['catalog/category', $type])],
                ['entity_id', 'attribute_id'],
            )
            ->joinLeft(
                ['store' => $this->getTable(['catalog/category', $type])],
                'store.entity_id = def.entity_id AND store.attribute_id = def.attribute_id '
                    . 'AND store.store_id = ' . $storeId,
                ['value' => $this->_getWriteAdapter()->getCheckSql(
                    'store.value_id > 0',
                    $this->_getWriteAdapter()->quoteIdentifier('store.value'),
                    $this->_getWriteAdapter()->quoteIdentifier('def.value'),
                )],
            )
            ->where('def.entity_id IN (?)', $entityIds)
            ->where('def.store_id IN (?)', [Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID, $storeId]);
        return $this->_getWriteAdapter()->fetchAll($select);
    }

    /**
     * Delete store table(s) of given stores;
     *
     * @param array|int $stores
     * @return $this
     */
    public function deleteStores($stores)
    {
        $this->_deleteTable($stores);
        return $this;
    }

    /**
     * Delete table(s) of given stores.
     *
     * @param array|int $stores
     * @return $this
     */
    protected function _deleteTable($stores)
    {
        if (!is_array($stores)) {
            $stores = [$stores];
        }
        foreach ($stores as $store) {
            $this->_getWriteAdapter()->dropTable($this->getMainStoreTable($store));
        }
        return $this;
    }

    /**
     * Synchronize flat data with eav model for category
     *
     * @param Varien_Object $category
     * @return $this
     */
    protected function _synchronize($category)
    {
        $table = $this->getMainStoreTable($category->getStoreId());
        $data  = $this->_prepareDataForAllFields($category);
        $this->_getWriteAdapter()->insertOnDuplicate($table, $data);
        return $this;
    }

    /**
     * Synchronize flat data with eav model.
     *
     * @param Mage_Catalog_Model_Category|int $category
     * @param array $storeIds
     * @return $this
     */
    public function synchronize($category = null, $storeIds = [])
    {
        if (is_null($category)) {
            if (empty($storeIds)) {
                $storeIds = null;
            }
            $stores = $this->getStoresRootCategories($storeIds);

            $storesObjects = [];
            foreach ($stores as $storeId => $rootCategoryId) {
                $_store = new Varien_Object([
                    'store_id'          => $storeId,
                    'root_category_id'  => $rootCategoryId,
                ]);
                $_store->setIdFieldName('store_id');
                $storesObjects[] = $_store;
            }

            $this->rebuild($storesObjects);
        } elseif ($category instanceof Mage_Catalog_Model_Category) {
            $categoryId = $category->getId();
            foreach ($category->getStoreIds() as $storeId) {
                if ($storeId == Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID) {
                    continue;
                }

                $attributeValues = $this->_getAttributeValues($categoryId, $storeId);
                $data = new Varien_Object($category->getData());
                $data->addData($attributeValues[$categoryId])
                    ->setStoreId($storeId);
                $this->_synchronize($data);
            }
        } elseif (is_numeric($category)) {
            $write  = $this->_getWriteAdapter();
            $select = $write->select()
                ->from($this->getTable('catalog/category'))
                ->where('entity_id=?', $category);
            $row    = $write->fetchRow($select);
            if (!$row) {
                return $this;
            }

            $stores = $this->getStoresRootCategories();
            $path   = explode('/', $row['path']);
            foreach ($stores as $storeId => $rootCategoryId) {
                if (in_array($rootCategoryId, $path)) {
                    $attributeValues = $this->_getAttributeValues($category, $storeId);
                    $data = new Varien_Object($row);
                    $data->addData($attributeValues[$category])
                        ->setStoreId($storeId);
                    $this->_synchronize($data);
                } else {
                    $where = $write->quoteInto('entity_id = ?', $category);
                    $write->delete($this->getMainStoreTable($storeId), $where);
                }
            }
        }

        return $this;
    }

    /**
     * Remove table of given stores
     *
     * @param int|array $stores
     * @return $this
     */
    public function removeStores($stores)
    {
        $this->_deleteTable($stores);
        return $this;
    }

    /**
     * Synchronize flat category data after move by affected category ids
     *
     * @return $this
     */
    public function move(array $affectedCategoryIds)
    {
        $write  = $this->_getWriteAdapter();
        $select = $write->select()
            ->from($this->getTable('catalog/category'), ['entity_id', 'path'])
            ->where('entity_id IN(?)', $affectedCategoryIds);
        $pairs  = $write->fetchPairs($select);

        $pathCond  = [$write->quoteInto('entity_id IN(?)', $affectedCategoryIds)];
        $parentIds = [];

        foreach ($pairs as $path) {
            $pathCond[] = $write->quoteInto('path LIKE ?', $path . '/%');
            $parentIds  = array_merge($parentIds, explode('/', $path));
        }

        $stores = $this->getStoresRootCategories();
        $where = implode(' OR ', $pathCond);
        $lastId = 0;
        while (true) {
            $select = $write->select()
                ->from($this->getTable('catalog/category'))
                ->where('entity_id>?', $lastId)
                ->where($where)
                ->order('entity_id')
                ->limit(500);
            $rowSet = $write->fetchAll($select);

            if (!$rowSet) {
                break;
            }

            $addStores = [];
            $remStores = [];

            foreach ($rowSet as &$row) {
                $lastId = $row['entity_id'];
                $path = explode('/', $row['path']);
                foreach ($stores as $storeId => $rootCategoryId) {
                    if (in_array($rootCategoryId, $path)) {
                        $addStores[$storeId][$row['entity_id']] = $row;
                    } else {
                        $remStores[$storeId][] = $row['entity_id'];
                    }
                }
            }

            // remove
            foreach ($remStores as $storeId => $categoryIds) {
                $where = $write->quoteInto('entity_id IN(?)', $categoryIds);
                $write->delete($this->getMainStoreTable($storeId), $where);
            }

            // add/update
            foreach ($addStores as $storeId => $storeCategoryIds) {
                $attributeValues = $this->_getAttributeValues(array_keys($storeCategoryIds), $storeId);
                foreach ($storeCategoryIds as $row) {
                    $data = new Varien_Object($row);
                    $data->addData($attributeValues[$row['entity_id']])
                        ->setStoreId($storeId);
                    $this->_synchronize($data);
                }
            }
        }

        return $this;
    }

    /**
     * Synchronize flat data with eav after moving category
     *
     * @param int $categoryId
     * @param int $prevParentId
     * @param int $parentId
     * @return $this
     */
    public function moveold($categoryId, $prevParentId, $parentId)
    {
        $catalogCategoryTable = $this->getTable('catalog/category');
        $_staticFields = [
            'parent_id',
            'path',
            'level',
            'position',
            'children_count',
            'updated_at',
        ];
        $prevParent = Mage::getModel('catalog/category')->load($prevParentId);
        $parent = Mage::getModel('catalog/category')->load($parentId);
        if ($prevParent->getStore()->getWebsiteId() != $parent->getStore()->getWebsiteId()) {
            foreach ($prevParent->getStoreIds() as $storeId) {
                $this->_getWriteAdapter()->delete(
                    $this->getMainStoreTable($storeId),
                    $this->_getWriteAdapter()->quoteInto('entity_id = ?', $categoryId),
                );
            }
            $select = $this->_getReadAdapter()->select()
                ->from($catalogCategoryTable, 'path')
                ->where('entity_id = ?', $categoryId);

            $categoryPath = $this->_getWriteAdapter()->fetchOne($select);

            $select = $this->_getWriteAdapter()->select()
                ->from($catalogCategoryTable, 'entity_id')
                ->where('path LIKE ?', "$categoryPath/%")
                ->orWhere('path = ?', $categoryPath);
            $_categories = $this->_getWriteAdapter()->fetchAll($select);
            foreach ($_categories as $_category) {
                foreach ($parent->getStoreIds() as $storeId) {
                    $_tmpCategory = Mage::getModel('catalog/category')
                        ->setStoreId($storeId)
                        ->load($_category['entity_id']);
                    $this->_synchronize($_tmpCategory);
                }
            }
        } else {
            foreach ($parent->getStoreIds() as $store) {
                $mainStoreTable = $this->getMainStoreTable($store);

                $update = "UPDATE {$mainStoreTable}, {$catalogCategoryTable} SET";
                foreach ($_staticFields as $field) {
                    $update .= " {$mainStoreTable}." . $field . "={$catalogCategoryTable}." . $field . ',';
                }
                $update = substr($update, 0, -1);
                $update .= " WHERE {$mainStoreTable}.entity_id = {$catalogCategoryTable}.entity_id AND " .
                    "($catalogCategoryTable}.path like '{$parent->getPath()}/%' OR " .
                    "{$catalogCategoryTable}.path like '{$prevParent->getPath()}/%')";
                $this->_getWriteAdapter()->query($update);
            }
        }
        $prevParent   = null;
        $parent       = null;
        $_tmpCategory = null;
        return $this;
    }

    /**
     * Prepare array of category data to insert or update.
     * array(
     *  'field_name' => 'value'
     * )
     *
     * @param Varien_Object $category
     * @param array $replaceFields
     * @return array
     */
    protected function _prepareDataForAllFields($category, $replaceFields = [])
    {
        $table = $this->getMainStoreTable($category->getStoreId());
        $this->_getWriteAdapter()->resetDdlCache($table);
        $table = $this->_getReadAdapter()->describeTable($table);
        $data = [];
        $idFieldName = Mage::getSingleton('catalog/category')->getIdFieldName();
        foreach (array_keys($table) as $column) {
            if ($column != $idFieldName || $category->getData($column) !== null) {
                if (array_key_exists($column, $replaceFields)) {
                    $value = $category->getData($replaceFields[$column]);
                } else {
                    $value = $category->getData($column);
                }
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                $data[$column] = $value;
            }
        }
        return $data;
    }

    /**
     * Retrieve attribute instance
     * Special for non static flat table
     *
     * @param mixed $attribute
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getAttribute($attribute)
    {
        return Mage::getSingleton('catalog/config')
            ->getAttribute(Mage_Catalog_Model_Category::ENTITY, $attribute);
    }

    /**
     * Get count of active/not active children categories
     *
     * @param Mage_Catalog_Model_Category $category
     * @param bool $isActiveFlag
     * @return int
     */
    public function getChildrenAmount($category, $isActiveFlag = true)
    {
        $_table = $this->getMainStoreTable($category->getStoreId());
        $select = $this->_getReadAdapter()->select()
            ->from($_table, "COUNT({$_table}.entity_id)")
            ->where("{$_table}.path LIKE ?", $category->getPath() . '/%')
            ->where("{$_table}.is_active = ?", (int) $isActiveFlag);
        return (int) $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Get products count in category
     *
     * @param Mage_Catalog_Model_Category $category
     * @return int
     */
    public function getProductCount($category)
    {
        $select =  $this->_getReadAdapter()->select()
            ->from(
                $this->getTable('catalog/category_product'),
                "COUNT({$this->getTable('catalog/category_product')}.product_id)",
            )
            ->where("{$this->getTable('catalog/category_product')}.category_id = ?", $category->getId())
            ->group("{$this->getTable('catalog/category_product')}.category_id");
        return (int) $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Get positions of associated to category products
     *
     * @param Mage_Catalog_Model_Category $category
     * @return array
     */
    public function getProductsPosition($category)
    {
        $select = $this->_getReadAdapter()->select()
            ->from(
                $this->getTable('catalog/category_product'),
                ['product_id', 'position'],
            )
            ->where('category_id = :category_id');
        $bind = ['category_id' => (int) $category->getId()];
        return $this->_getReadAdapter()->fetchPairs($select, $bind);
    }

    /**
     * Return parent categories of category
     *
     * @param Mage_Catalog_Model_Category $category
     * @param bool $isActive
     * @return array
     */
    public function getParentCategories($category, $isActive = true)
    {
        $categories = [];
        $select = $this->_getReadAdapter()->select()
            ->from(
                ['main_table' => $this->getMainStoreTable($category->getStoreId())],
                ['main_table.entity_id', 'main_table.name'],
            )
            ->where('main_table.entity_id IN (?)', array_reverse(explode(',', $category->getPathInStore())));
        if ($isActive) {
            $select->where('main_table.is_active = ?', '1');
        }
        $select->order('main_table.path ASC');

        $urlRewrite = $this->_factory->getCategoryUrlRewriteHelper();
        $urlRewrite->joinTableToSelect($select, $category->getStoreId());

        $result = $this->_getReadAdapter()->fetchAll($select);
        foreach ($result as $row) {
            $row['id'] = $row['entity_id'];
            $categories[$row['entity_id']] = Mage::getModel('catalog/category')->setData($row);
        }
        return $categories;
    }

    /**
     * Return parent category of current category with own custom design settings
     *
     * @param Mage_Catalog_Model_Category $category
     * @return Mage_Catalog_Model_Category
     */
    public function getParentDesignCategory($category)
    {
        $adapter    = $this->_getReadAdapter();
        $levelField = $adapter->quoteIdentifier('level');
        $pathIds    = array_reverse($category->getPathIds());
        $select = $adapter->select()
            ->from(['main_table' => $this->getMainStoreTable($category->getStoreId())], '*')
            ->where('entity_id IN (?)', $pathIds)
            ->where('custom_use_parent_settings = ?', 0)
            ->where($levelField . ' != ?', 0)
            ->order('level ' . Varien_Db_Select::SQL_DESC);
        $result = $adapter->fetchRow($select);
        return Mage::getModel('catalog/category')->setData($result);
    }

    /**
     * Return children categories of category
     *
     * @param Mage_Catalog_Model_Category $category
     * @return array
     */
    public function getChildrenCategories($category)
    {
        return $this->_loadNodes($category, 1, $category->getStoreId());
    }

    /**
     * Return children categories of category with inactive
     *
     * @param Mage_Catalog_Model_Category $category
     * @return array
     */
    public function getChildrenCategoriesWithInactive($category)
    {
        return $this->_loadNodes($category, 1, $category->getStoreId(), false);
    }

    /**
     * Check is category in list of store categories
     *
     * @param Mage_Catalog_Model_Category $category
     * @return bool
     */
    public function isInRootCategoryList($category)
    {
        $pathIds = $category->getParentIds();
        return in_array(Mage::app()->getStore()->getRootCategoryId(), $pathIds);
    }

    /**
     * Return children ids of category
     *
     * @param Mage_Catalog_Model_Category $category
     * @param bool $recursive
     * @param bool $isActive
     * @return array
     */
    public function getChildren($category, $recursive = true, $isActive = true)
    {
        $maintable = $this->getMainStoreTable($category->getStoreId());
        $select = $this->_getReadAdapter()->select()
            ->from($maintable, 'entity_id')
            ->where('path LIKE ?', "{$category->getPath()}/%")
            ->order($maintable . '.position ASC');
        if (!$recursive) {
            $select->where('level <= ?', $category->getLevel() + 1);
        }
        if ($isActive) {
            $select->where('is_active = ?', '1');
        }

        $_categories = $this->_getReadAdapter()->fetchAll($select);
        $categoriesIds = [];
        foreach ($_categories as $_category) {
            $categoriesIds[] = $_category['entity_id'];
        }
        return $categoriesIds;
    }

    /**
     * Return all children ids of category (with category id)
     *
     * @param Mage_Catalog_Model_Category $category
     * @return array
     */
    public function getAllChildren($category)
    {
        $categoriesIds = $this->getChildren($category);
        $myId = [$category->getId()];

        return array_merge($myId, $categoriesIds);
    }

    /**
     * Check if category id exist
     *
     * @param int $id
     * @return bool
     */
    public function checkId($id)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainStoreTable($this->getStoreId()), 'entity_id')
            ->where('entity_id=?', $id);
        return $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Get design update data of parent categories
     *
     * @param Mage_Catalog_Model_Category $category
     * @return array
     */
    public function getDesignUpdateData($category)
    {
        $categories = [];
        $pathIds = [];
        foreach (array_reverse($category->getParentIds()) as $pathId) {
            if ($pathId == Mage::app()->getStore()->getRootCategoryId()) {
                $pathIds[] = $pathId;
                break;
            }
            $pathIds[] = $pathId;
        }
        $select = $this->_getReadAdapter()->select()
            ->from(
                ['main_table' => $this->getMainStoreTable($category->getStoreId())],
                [
                    'main_table.entity_id',
                    'main_table.custom_design',
                    'main_table.custom_design_apply',
                    'main_table.custom_design_from',
                    'main_table.custom_design_to',
                ],
            )
            ->where('main_table.entity_id IN (?)', $pathIds)
            ->where('main_table.is_active = ?', '1')
            ->order('main_table.path ' . Varien_Db_Select::SQL_DESC);
        $result = $this->_getReadAdapter()->fetchAll($select);
        foreach ($result as $row) {
            $row['id'] = $row['entity_id'];
            $categories[$row['entity_id']] = Mage::getModel('catalog/category')->setData($row);
        }
        return $categories;
    }

    /**
     * Retrieve anchors above
     *
     * @param int $storeId
     * @return array
     */
    public function getAnchorsAbove(array $filterIds, $storeId = 0)
    {
        $select = $this->_getReadAdapter()->select()
            ->from(['e' => $this->getMainStoreTable($storeId)], 'entity_id')
            ->where('is_anchor = ?', 1)
            ->where('entity_id IN (?)', $filterIds);

        return $this->_getReadAdapter()->fetchCol($select);
    }

    /**
     * Retrieve array with root category id per store
     *
     * @param int|array $storeIds   result limitation
     * @return array
     */
    public function getStoresRootCategories($storeIds = null)
    {
        if (is_null($this->_storesRootCategories)) {
            $select = $this->_getWriteAdapter()->select()
                ->from(['cs' => $this->getTable('core/store')], ['store_id'])
                ->join(
                    ['csg' => $this->getTable('core/store_group')],
                    'csg.group_id = cs.group_id',
                    ['root_category_id'],
                )
                ->where('cs.store_id <> ?', Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
            $this->_storesRootCategories = $this->_getWriteAdapter()->fetchPairs($select);
        }

        if (!is_null($storeIds)) {
            if (!is_array($storeIds)) {
                $storeIds = [$storeIds];
            }

            $stores = [];
            foreach ($this->_storesRootCategories as $storeId => $rootId) {
                if (in_array($storeId, $storeIds)) {
                    $stores[$storeId] = $rootId;
                }
            }
            return $stores;
        }

        return $this->_storesRootCategories;
    }

    /**
     * Creating table and adding attributes as fields to table for all stores
     *
     * @return $this
     */
    protected function _createTables()
    {
        if ($this->_allowTableChanges) {
            foreach (Mage::app()->getStores() as $store) {
                $this->_createTable($store->getId());
            }
        }
        return $this;
    }

    /**
     * Transactional rebuild flat data from eav
     *
     * @throws Exception
     * @return $this
     */
    public function reindexAll()
    {
        $this->_createTables();
        $allowTableChanges = $this->_allowTableChanges;
        if ($allowTableChanges) {
            $this->_allowTableChanges = false;
        }
        $this->beginTransaction();
        try {
            $this->rebuild();
            $this->commit();
            if ($allowTableChanges) {
                $this->_allowTableChanges = true;
            }
        } catch (Exception $e) {
            $this->rollBack();
            if ($allowTableChanges) {
                $this->_allowTableChanges = true;
            }
            throw $e;
        }
        return $this;
    }

    /**
     * Check if Catalog Category Flat Data has been initialized
     *
     * @deprecated use Mage_Catalog_Model_Resource_Category_Flat::isBuilt() instead
     *
     * @return bool
     */
    public function isRebuilt()
    {
        return $this->isBuilt();
    }
}
