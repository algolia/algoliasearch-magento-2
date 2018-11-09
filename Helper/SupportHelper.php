<?php

namespace Algolia\AlgoliaSearch\Helper;

class SupportHelper
{
    const INTERNAL_API_PROXY_URL = 'https://lj1hut7upg.execute-api.us-east-2.amazonaws.com/dev/';

    /** @var ConfigHelper */
    private $configHelper;

    /** @param ConfigHelper $configHelper */
    public function __construct(ConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * @param array $data
     * @return bool
     */
    public function processContactForm($data)
    {
        $data = $this->getMessageData($data);

        $text = $this->enrichText($data['message']);
        list($firstname, $lastname) = $this->splitName($data['name']);

        $messageData = [
            'email' => $data['email'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'subject' => $data['subject'],
            'text' => $text,
        ];

        return $this->pushMessage($messageData);
    }

    /** @return bool */
    public function isExtensionSupportEnabled()
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

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::INTERNAL_API_PROXY_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $res = curl_exec($ch);

        curl_close ($ch);

        if ($res) {
            $res = json_decode($res, true);
        }

        return $res['extension_support'];
    }


    /**
     * @param array $data
     * @return array
     */
    private function getMessageData($data)
    {
        $attributes = ['name', 'email', 'subject', 'message'];

        $cleanData = [];

        foreach ($attributes as $attribute) {
            $cleanData[$attribute] = $data[$attribute];
        }

        return $cleanData;
    }

    /**
     * @param string $text
     * @return string
     */
    private function enrichText($text)
    {
        // TODO
        return $text;
    }

    /**
     * @param string $name
     * @return array
     */
    private function splitName($name)
    {
        return explode(' ', $name, 2);
    }

    /**
     * @param array $messageData
     * @return bool
     */
    private function pushMessage($messageData)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::INTERNAL_API_PROXY_URL . 'hs-push/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $res = curl_exec($ch);

        curl_close ($ch);

        if ($res === 'true') {
            return true;
        }

        return false;
    }
}
