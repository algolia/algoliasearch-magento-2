<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Analytics;

class Form extends Index
{
    /**
     * @return string
     */
    public function getFormAction()
    {
        return $this->getBackendView()->getUrlInterface()->getUrl('*/*/update', ['_current' => true]);
    }

    /**
     * Set Default Date Range if Form Value for dates are not set
     * @param $key
     * @return string
     */
    public function getFormValue($key)
    {
        $formData = $this->getBackendView()->getBackendSession()->getAlgoliaAnalyticsFormData();
        if ((!isset($formData['to']) && !isset($formData['from']))
            || ($formData['to'] == '' && $formData['from'] == '')) {
            $formData['to'] = date('d M Y', time());
            $formData['from'] = date('d M Y', strtotime('-7 day'));
        }

        return ($formData && isset($formData[$key])) ? $formData[$key] : '';
    }
}