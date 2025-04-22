<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AutocompleteRedirectMode implements OptionSourceInterface
{
    public const SUBMIT_ONLY = 0;
    public const SELECTABLE_REDIRECT = 1;
    public const REDIRECT_WITH_HITS = 2;

    /** @return array */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::SUBMIT_ONLY,
                'label' => __('Do not display the redirect (handle on form submit only)'),
            ],
            [
                'value' => self::SELECTABLE_REDIRECT,
                'label' => __('Display the redirect as a selectable item in place of search hits'),
            ],
            [
                'value' => self::REDIRECT_WITH_HITS,
                'label' => __('Display both search hits and a selectable redirect'),
            ]
        ];

        return $options;
    }
}
