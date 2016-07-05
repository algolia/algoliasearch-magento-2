<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

/**
 * Algolia custom sort order field
 */
abstract class AbstractTable extends AbstractFieldArray
{
    protected $selectFields = [];
    protected $productHelper;
    protected $categoryHelper;
    protected $config;

    abstract protected function getTableData();

    public function __construct(Context $context,
                                ProductHelper $producthelper,
                                CategoryHelper $categoryHelper,
                                ConfigHelper $configHelper,
                                array $data = [])
    {
        $this->config = $configHelper;
        $this->productHelper = $producthelper;
        $this->categoryHelper = $categoryHelper;

        parent::__construct($context, $data);
    }

    protected function getRenderer($columnId, $columnData)
    {
        if (!array_key_exists($columnId, $this->selectFields) || !$this->selectFields[$columnId]) {
            $select = $this->getLayout()->createBlock('Algolia\AlgoliaSearch\Block\System\Form\Field\Select', '', ['data' => ['is_render_to_js_template' => true]]);

            $options = $columnData['values'];

            if (is_callable($options)) {
                $options = $options();
            }

            $extraParams = $columnId === 'attribute' ? 'style="width:160px;"' : 'style="width:100px;"';
            $select->setExtraParams($extraParams);
            $select->setOptions($options);

            $this->selectFields[$columnId] = $select;
        }

        return $this->selectFields[$columnId];
    }

    protected function _construct()
    {
        $data = $this->getTableData();

        foreach (array_keys($data) as $columnId) {
            $columnData = $data[$columnId];

            $column = [
                'label' => __($columnData['label']),
            ];

            if (isset($columnData['values'])) {
                $column['renderer'] = $this->getRenderer($columnId, $columnData);
            }

            if (isset($columnData['class'])) {
                $column['class'] = $columnData['class'];
            }

            if (isset($columnData['style'])) {
                $column['style'] = $columnData['style'];
            }

            $this->addColumn($columnId, $column);
        }

        $this->_addAfter = false;
        parent::_construct();
    }

    protected function _prepareArrayRow(DataObject $row)
    {
        $data = $this->getTableData();
        $options = [];
        foreach (array_keys($data) as $columnId) {
            $columnData = $data[$columnId];

            if (isset($columnData['values'])) {
                $options['option_'.$this->getRenderer($columnId, $columnData)->calcOptionHash($row->getData($columnId))] = 'selected="selected"';
            }
        }

        $row->setData('option_extra_attrs', $options);
    }
}
