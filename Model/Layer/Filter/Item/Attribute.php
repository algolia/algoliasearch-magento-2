<?php

namespace Algolia\AlgoliaSearch\Model\Layer\Filter\Item;

/**
  * @method bool getIsSelected()
  * @method mixed getValue()
  * @method mixed getApplyFilterValue()
  * @method string getLabel()
  */
class Attribute extends \Magento\Catalog\Model\Layer\Filter\Item
{
    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        $qsParams = [
            $this->getFilter()->getRequestVar() => $this->getApplyValue(),
            $this->_htmlPagerBlock->getPageVarName() => null,
        ];

        $qsParams =  $this->getAdditionalParams($qsParams);

        return $this->_url->getUrl(
            '*/*/*',
            [
                '_current' => true,
                '_use_rewrite' => true,
                '_escape' => false,
                '_query' => $qsParams,
            ]
        );
    }

    public function getRemoveUrl()
    {
        $query = [$this->getFilter()->getRequestVar() => $this->getFilter()->getResetValue()];

        if (is_array($this->getApplyValue())) {
            $idToRemove = null;

            foreach ($this->getFilter()->getAttributeModel()->getOptions() as $option) {
                if ($option->getLabel() == $this->getLabel()) {
                    $idToRemove = $option->getValue();
                    break;
                }
            }

            $resetValue = [];

            if (!is_null($idToRemove)) {
                $resetValue = array_diff($this->getApplyValue(), [$idToRemove]);
            }

            $query = [$this->getFilter()->getRequestVar() => implode('~', $resetValue)];
        }

        $params = [
            '_current'     => true,
            '_use_rewrite' => true,
            '_query'       => $query,
            '_escape'      => true,
        ];

        return $this->_url->getUrl('*/*/*', $params);
    }

    public function toArray(array $keys = [])
    {
        $data = parent::toArray($keys);

        if (in_array('url', $keys) || empty($keys)) {
            $data['url'] = $this->getUrl();
        }

        if (in_array('is_selected', $keys) || empty($keys)) {
            $data['is_selected'] = (bool) $this->getIsSelected();
        }

        return $data;
    }

    private function getApplyValue()
    {
        $value = $this->getValue();

        if (is_array($this->getApplyFilterValue())) {
            $value = $this->getApplyFilterValue();
        }

        if (is_array($value) && count($value) == 1) {
            $value = current($value);
        }

        return $value;
    }

    private function getAdditionalParams($qsParams)
    {
        $baseUrlParts = explode('?', htmlspecialchars_decode($this->_url->getCurrentUrl()));

        $qsParser = new \Zend\Stdlib\Parameters();
        $qsParser->fromArray($qsParams);

        $paramsToAdd = $qsParser->toArray();

        if (count($baseUrlParts) > 1) {
            $qsParser->fromString($baseUrlParts[1]);
            $existingParams = $qsParser->toArray();

            foreach ($paramsToAdd as $key => $value) {
                if (isset($existingParams[$key])) {
                    $existingParamsArray = explode('~', $existingParams[$key]);
                    // check if the value is already in the applied filter, if it is, this means we have to remove it
                    if (in_array($paramsToAdd[$key], $existingParamsArray) && $this->getIsSelected()) {
                        foreach ($existingParamsArray as $arrayParamKey => $arrayParam) {
                            if ($arrayParam == $paramsToAdd[$key]) {
                                unset($existingParamsArray[$arrayParamKey]);
                            }
                        }
                        $paramsToAdd[$key] = !empty($existingParamsArray) ?
                            implode('~', $existingParamsArray) :
                            null;
                    } else {
                        $paramsToAdd[$key] =  $existingParams[$key] . '~' . $paramsToAdd[$key];
                    }
                }
            }

            $qsParams = array_merge($existingParams, $paramsToAdd);
            $qsParser->fromArray($qsParams);
        }

        $baseUrlParts[1] = $qsParser->toString();

        return $qsParser->toArray();
    }
}
