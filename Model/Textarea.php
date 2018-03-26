<?php

namespace Algolia\AlgoliaSearch\Model;

class Textarea extends \Magento\Framework\Data\Form\Element\Textarea
{
    public function getCols()
    {
        $this->setData('cols', 80);

        return 80;
    }

    public function getRows()
    {
        $this->setData('rows', 5);

        return 5;
    }
}
