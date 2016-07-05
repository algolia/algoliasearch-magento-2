<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Config\Model\Config\Backend\File;

class SynonymsFile extends File
{
    protected function _getAllowedExtensions()
    {
        return ['json'];
    }
}
