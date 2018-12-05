<?php

namespace Algolia\AlgoliaSearch\Helper;

class ProxyHelper
{
    const PROXY_URL = 'https://magento-proxy.algolia.com/';

    const INFO_TYPE_EXTENSION_SUPPORT = 'extension_support';
    const INFO_TYPE_ANALYTICS = 'analytics';

    /** @var ConfigHelper */
    private $configHelper;

    /** @param ConfigHelper $configHelper */
    public function __construct(ConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * @param string $type
     *
     * @return string|array
     */
    public function getInfo($type)
    {
        $appId = $this->configHelper->getApplicationID();
        $apiKey = $this->configHelper->getAPIKey();

        $token = $appId . ':' . $apiKey;
        $token = base64_encode($token);
        $token = str_replace(["\n", '='], '', $token);

        $params = [
            'appId' => $appId,
            'token' => $token,
        ];

        if ($type === self::INFO_TYPE_ANALYTICS) {
            $params['type'] = 'analytics';
        }

        $info = $this->postRequest($params);

        if ($info) {
            $info = json_decode($info, true);
        }

        return $info;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function pushSupportTicket($data)
    {
        $result = $this->postRequest($data);

        if ($result === 'true') {
            return true;
        }

        return false;
    }

    /**
     * @param $data
     *
     * @return bool|string
     */
    private function postRequest($data)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::PROXY_URL . 'hs-push/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }
}
