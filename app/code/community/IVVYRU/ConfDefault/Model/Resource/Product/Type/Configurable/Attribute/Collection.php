<?php
class IVVYRU_ConfDefault_Model_Resource_Product_Type_Configurable_Attribute_Collection
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected $_labelTable;

    protected $_priceTable;
     
    protected $_defaultsTable;

    protected $_product;

    protected function _construct()
    {
        $this->_init('catalog/product_type_configurable_attribute');
        $this->_labelTable = $this->getTable('catalog/product_super_attribute_label');
        $this->_priceTable = $this->getTable('catalog/product_super_attribute_pricing');
        $this->_defaultsTable = $this->getTable('confdefault/confdefault');
    }

    public function getHelper()
    {
        return Mage::helper('catalog');
    }

    public function setProductFilter($product)
    {
        $this->_product = $product;
        return $this->addFieldToFilter('product_id', $product->getId());
    }

    public function orderByPosition($dir = self::SORT_ORDER_ASC)
    {
        $this->setOrder('position ',  $dir);
        return $this;
    }

    public function getStoreId()
    {
        return (int)$this->_product->getStoreId();
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();
        Varien_Profiler::start('TTT1:'.__METHOD__);
        $this->_addProductAttributes();
        Varien_Profiler::stop('TTT1:'.__METHOD__);
        Varien_Profiler::start('TTT2:'.__METHOD__);
        $this->_addAssociatedProductFilters();
        Varien_Profiler::stop('TTT2:'.__METHOD__);
        Varien_Profiler::start('TTT3:'.__METHOD__);
        $this->_loadLabels();
        Varien_Profiler::stop('TTT3:'.__METHOD__);
        Varien_Profiler::start('TTT4:'.__METHOD__);
        $this->_loadPrices();
        Varien_Profiler::stop('TTT4:'.__METHOD__);
        Varien_Profiler::start('TTT5:'.__METHOD__);
        $this->_loadDefaults();
        Varien_Profiler::stop('TTT5:'.__METHOD__);
        return $this;
    }

    protected function _addProductAttributes()
    {
        foreach ($this->_items as $item) {
            $productAttribute = $this->getProduct()->getTypeInstance(true)
                ->getAttributeById($item->getAttributeId(), $this->getProduct());
            $item->setProductAttribute($productAttribute);
        }
        return $this;
    }

    public function _addAssociatedProductFilters()
    {
        $this->getProduct()->getTypeInstance(true)
            ->getUsedProducts($this->getColumnValues('attribute_id'), $this->getProduct()); // Filter associated products
        return $this;
    }

    protected function _loadLabels()
    {
        if ($this->count()) {
            $useDefaultCheck = $this->getConnection()->getCheckSql(
                'store.use_default IS NULL',
                'def.use_default',
                'store.use_default'
            );

            $labelCheck = $this->getConnection()->getCheckSql(
                'store.value IS NULL',
                'def.value',
                'store.value'
            );

            $select = $this->getConnection()->select()
                ->from(array('def' => $this->_labelTable))
                ->joinLeft(
                    array('store' => $this->_labelTable),
                    $this->getConnection()->quoteInto('store.product_super_attribute_id = def.product_super_attribute_id AND store.store_id = ?', $this->getStoreId()),
                    array(
                        'use_default' => $useDefaultCheck,
                        'label' => $labelCheck
                    ))
                ->where('def.product_super_attribute_id IN (?)', array_keys($this->_items))
                ->where('def.store_id = ?', 0);

                $result = $this->getConnection()->fetchAll($select);
                foreach ($result as $data) {
                    $this->getItemById($data['product_super_attribute_id'])->setLabel($data['label']);
                    $this->getItemById($data['product_super_attribute_id'])->setUseDefault($data['use_default']);
                }
        }
        return $this;
    }

    /* copy-paste from _loadLables */
    protected function _loadDefaults()
    {
       if ($this->count()) {
          $select = $this->getConnection()->select()
                    ->from(array('dd' => $this->_defaultsTable))
                    ->where('dd.store_id = ?', ($this->getStoreId ? $this->getStoreId() : 0))   
                    ->where('dd.product_super_attribute_id IN (?)', array_keys($this->_items));
          $result = $this->getConnection()->fetchAll($select);
          $prefvalues = array();
          foreach($result as $data) {
               $prefvalues[$this->getItemById($data['product_super_attribute_id'])->getAttributeId()]  = $data['value_index'];
               $this->getItemById($data['product_super_attribute_id'])->setSuperDefault($data['value_index']);
          }
           $this->getProduct()->setData('preconfigured_values',$this->getProduct()->getPreconfiguredValues());
           $this->getProduct()
                    ->getPreconfiguredValues()
                    ->setData('super_attribute',$prefvalues);
       }
       return $this;    
    }

    protected function _loadPrices()
    {
        if ($this->count()) {
            $pricings = array(
                0 => array()
            );

            if ($this->getHelper()->isPriceGlobal()) {
                $websiteId = 0;
            } else {
                $websiteId = (int)Mage::app()->getStore($this->getStoreId())->getWebsiteId();
                $pricing[$websiteId] = array();
            }

            $select = $this->getConnection()->select()
                ->from(array('price' => $this->_priceTable))
                ->where('price.product_super_attribute_id IN (?)', array_keys($this->_items));

            if ($websiteId > 0) {
                $select->where('price.website_id IN(?)', array(0, $websiteId));
            } else {
                $select->where('price.website_id = ?', 0);
            }

            $query = $this->getConnection()->query($select);

            while ($row = $query->fetch()) {
                $pricings[(int)$row['website_id']][] = $row;
            }

            $values = array();

            foreach ($this->_items as $item) {
               $productAttribute = $item->getProductAttribute();
               if (!($productAttribute instanceof Mage_Eav_Model_Entity_Attribute_Abstract)) {
                   continue;
               }
               $options = $productAttribute->getFrontend()->getSelectOptions();
               foreach ($options as $option) {
                   foreach ($this->getProduct()->getTypeInstance(true)->getUsedProducts(null, $this->getProduct()) as $associatedProduct) {
                        if (!empty($option['value'])
                            && $option['value'] == $associatedProduct->getData(
                                                        $productAttribute->getAttributeCode())) {
                            // If option available in associated product
                            if (!isset($values[$item->getId() . ':' . $option['value']])) {
                                // If option not added, we will add it.
                                $values[$item->getId() . ':' . $option['value']] = array(
                                    'product_super_attribute_id' => $item->getId(),
                                    'value_index'                => $option['value'],
                                    'label'                      => $option['label'],
                                    'default_label'              => $option['label'],
                                    'store_label'                => $option['label'],
                                    'is_percent'                 => 0,
                                    'pricing_value'              => null,
                                    'use_default_value'          => true
                                );
                            }
                        }
                   }
               }
            }

            foreach ($pricings[0] as $pricing) {
                // Addding pricing to options
                $valueKey = $pricing['product_super_attribute_id'] . ':' . $pricing['value_index'];
                if (isset($values[$valueKey])) {
                    $values[$valueKey]['pricing_value']     = $pricing['pricing_value'];
                    $values[$valueKey]['is_percent']        = $pricing['is_percent'];
                    $values[$valueKey]['value_id']          = $pricing['value_id'];
                    $values[$valueKey]['use_default_value'] = true;
                }
            }

            if ($websiteId && isset($pricings[$websiteId])) {
                foreach ($pricings[$websiteId] as $pricing) {
                    $valueKey = $pricing['product_super_attribute_id'] . ':' . $pricing['value_index'];
                    if (isset($values[$valueKey])) {
                        $values[$valueKey]['pricing_value']     = $pricing['pricing_value'];
                        $values[$valueKey]['is_percent']        = $pricing['is_percent'];
                        $values[$valueKey]['value_id']          = $pricing['value_id'];
                        $values[$valueKey]['use_default_value'] = false;
                    }
                }
            }

            foreach ($values as $data) {
                $this->getItemById($data['product_super_attribute_id'])->addPrice($data);
            }
        }
        return $this;
    }

    public function getProduct()
    {
        return $this->_product;
    }
}
