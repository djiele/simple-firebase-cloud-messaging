<?php

namespace Djiele\Fcm;

class WebPushNotifications
{
    const FCM_URL = 'https://fcm.googleapis.com/fcm/send';
    const FCM_GRP_URL = 'https://fcm.googleapis.com/fcm/notification';
    const IID_BATCH_URL = 'https://iid.googleapis.com/iid/v1:batch%s';
    const IID_SUBSCRIBE_SINGLE_URL = 'https://iid.googleapis.com/iid/v1/%s/rel/topics/%s';
    const IID_INFOS_URL = 'https://iid.googleapis.com/iid/info/%s?details=true';

    private $serverKey;
    private $senderId;

    protected $curl;
    protected $curlVerbose;
    protected $groupNotificationKeys;

    /**
     * WebPushNotifications constructor.
     * @param $serverKey
     * @param $senderId
     */
    public function __construct($serverKey, $senderId)
    {
        $this->serverKey = $serverKey;
        $this->senderId = $senderId;
        $this->curlVerbose = false;
        $this->groupNotificationKeys = [];
    }

    /**
     * WebPushNotifications destructor.
     */
    public function __destruct()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        $this->curl = null;
    }

    /**
     * Send notification to one or more devices
     *
     * @param array $tokens
     * @param $title
     * @param $contents
     * @param array|null $extras
     * @return bool|mixed|string|null
     */
    public function sendTo(array $tokens, $title, $contents, array $extras = null)
    {
        if (0 < count($tokens)) {
            $query = $this->messageBuilder($title, $contents, $extras);
            $query['registration_ids'] = $tokens;
            return $this->jsonPost(self::FCM_URL, $query);
        } else {
            return null;
        }
    }

    /**
     * Get the group notification key from cache or fetch it if unset
     *
     * @param $groupName
     * @return mixed|null
     */
    public function getGroupNotificationKey($groupName)
    {
        if (array_key_exists($groupName, $this->groupNotificationKeys)) {
            $ret = $this->groupNotificationKeys[$groupName];
        } else {
            $tmp = $this->fetchGroupNotificationKey($groupName);
            if (isset($tmp->notification_key)) {
                $this->groupNotificationKeys[$groupName] = $tmp->notification_key;
                $ret = $this->groupNotificationKeys[$groupName];
            } else {
                $ret = null;
            }
        }
        return $ret;
    }

    /**
     * Create a group of devices (max 20)
     * @param $groupName
     * @param array $tokens
     * @return bool|mixed|string
     */
    public function createGroup($groupName, array $tokens)
    {
        $query = [
            'operation' => 'create',
            'notification_key_name' => $groupName,
            'registration_ids' => $tokens,
        ];
        $response = $this->jsonPost(self::FCM_GRP_URL, $query, ['headers' => ['project_id' => $this->senderId]]);
        if (isset($response->notification_key)) {
            $this->groupNotificationKeys[$groupName] = $response->notification_key;
        }
        return $response;
    }

    /**
     * Add one or more devices to a group of devices
     *
     * @param $groupName
     * @param array $tokens
     * @return bool|mixed|string
     */
    public function addToGroup($groupName, array $tokens)
    {
        $query = [
            'operation' => 'add',
            'notification_key' => $this->getGroupNotificationKey($groupName),
            'notification_key_name' => $groupName,
            'registration_ids' => $tokens,
        ];

        return $this->jsonPost(self::FCM_GRP_URL, $query, ['headers' => ['project_id' => $this->senderId]]);
    }

    /**
     * Remove one or more devices from a group of devices
     * Group is deleted from server when device count equals 0
     *
     * @param $groupName
     * @param array $tokens
     * @return bool|mixed|string
     */
    public function removeFromGroup($groupName, array $tokens)
    {
        $query = [
            'operation' => 'remove',
            'notification_key' => $this->getGroupNotificationKey($groupName),
            'notification_key_name' => $groupName,
            'registration_ids' => $tokens,
        ];

        return $this->jsonPost(self::FCM_GRP_URL, $query, ['headers' => ['project_id' => $this->senderId]]);
    }

    /**
     * Send a notification fo a group of devices
     *
     * @param $groupName
     * @param $title
     * @param $contents
     * @param array|null $extras
     * @return bool|mixed|string
     */
    public function sendToGroup($groupName, $title, $contents, array $extras = null)
    {
        $query = $this->messageBuilder($title, $contents, $extras);
        $query['to'] = $groupName;

        return $this->jsonPost(self::FCM_URL, $query);
    }

    /**
     * Retrieve from IID server the notification key for given group name
     *
     * @param $groupName
     * @return bool|mixed|string
     */
    public function fetchGroupNotificationKey($groupName)
    {
        $url = self::FCM_GRP_URL . '?' . http_build_query(['notification_key_name' => $groupName]);
        return $this->jsonGet(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'project_id' => $this->senderId,
                ]
            ]
        );
    }

    /**
     * Add one or more devices (max 1000) to given topic
     * Topic is created on the fly if not exists
     *
     * @param $topic
     * @param array $tokens
     * @return bool|mixed|string
     */
    public function batchSubscribeTopic($topic, array $tokens)
    {
        $query = [
            'to' => '/topics/' . $topic,
            'registration_tokens' => $tokens,
        ];
        return $this->jsonPost(sprintf(self::IID_BATCH_URL, 'Add'), $query);
    }

    /**
     * Remove one or more devices (max 1000) from given topic
     *
     * @param $topic
     * @param array $tokens
     * @return bool|mixed|string
     */
    public function batchUnsubscribeTopic($topic, array $tokens)
    {
        $query = [
            'to' => '/topics/' . $topic,
            'registration_tokens' => $tokens,
        ];
        return $this->jsonPost(sprintf(self::IID_BATCH_URL, 'Remove'), $query);
    }

    /**
     * Subscribes a single device to given topic
     * Topic is created on the fly if not exists
     *
     * @param $topic
     * @param $token
     * @return bool|mixed|string
     */
    public function subscribeTopic($topic, $token)
    {
        return $this->jsonPost(sprintf(self::IID_SUBSCRIBE_SINGLE_URL, $token, $topic), []);
    }

    /**
     * Send a notification to given topic
     *
     * @param $topic
     * @param $title
     * @param $contents
     * @param array|null $extras
     * @return bool|mixed|string
     */
    public function sendToTopic($topic, $title, $contents, array $extras = null)
    {
        $query = $this->messageBuilder($title, $contents, $extras);
        $query['to'] = '/topics/' . $topic;

        return $this->jsonPost(self::FCM_URL, $query);
    }

    /**
     * Get IID server infos for given device
     * @param $token
     * @return bool|mixed|string
     */
    public function tokenInfos($token)
    {
        return $this->jsonPost(sprintf(self::IID_INFOS_URL, $token), ['details' => true]);
    }

    /**
     * Return structured data of a notification
     *
     * @param $title
     * @param $contents
     * @param $data array|null $extras
     * @param string $expires
     * @param null $iconUrl
     * @return array
     */
    public function messageBuilder($title, $contents, array $data = null, $expires = '1 hour', $iconUrl = null)
    {
        $message = [];
        if (empty($title) || empty($contents)) {
            $notification = null;
        } else {
            $notification = [
                'title' => $title,
                'body' => $contents,
            ];
            if(null !== $icon && in_array(parse_url($iconUrl, PHP_URL_SCHEME), ['http', 'https'])) {
                $notification['icon'] = $iconUrl;
            }
            if (is_array($data) && 0 < count($data)) {
                if (isset($data['link_action']) && !empty($data['link_action'])) {
                    $notification['link_action'] = $data['link_action'];
                    unset($data['link_action']);
                }
            }
            $message['notification'] = $notification;
        }

        if (is_array($data) && 0 < count($data)) {
            $message['data'] = $data;
        }

        if (!empty($expires)) {
            $message['webpush']['headers']['TTL'] = strtotime('+' . $expires) - time();
        }

        return $message;
    }

    /**
     * HTTP Post notification to REST endpoint
     *
     * @param $url
     * @param array|null $data
     * @param array|null $options
     * @return bool|mixed|string
     */
    public function jsonPost($url, array $data = null, array $options = null)
    {
        $headers = ['Authorization: key=' . $this->serverKey, 'Content-Type: application/json'];
        if (null !== $options && isset($options['headers'])) {
            foreach ($options['headers'] as $k => $v) {
                $headers[] = $k . ': ' . $v;
            }
        }
        if (!is_resource($this->curl)) {
            $this->curl = curl_init();
        }
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_VERBOSE, $this->curlVerbose);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, is_scalar($data) ? $data : json_encode($data));
        $response = curl_exec($this->curl);
        if ('' !== $response && '{' == $response[0] && '}' == substr($response, -1)) {
            $response = json_decode($response);
        }
        return $response;
    }

    /**
     * HTTP Get data from REST endpoint
     *
     * @param $url
     * @param array|null $options
     * @return bool|mixed|string
     */
    public function jsonGet($url, array $options = null)
    {
        $headers = ['Authorization: key=' . $this->serverKey];
        if (null !== $options && isset($options['headers'])) {
            foreach ($options['headers'] as $k => $v) {
                $headers[] = $k . ': ' . $v;
            }
        }
        if (!is_resource($this->curl)) {
            $this->curl = curl_init();
        }
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_VERBOSE, $this->curlVerbose);
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($this->curl);
        if ('' !== $response && '{' == $response[0] && '}' == substr($response, -1)) {
            $response = json_decode($response);
        }
        return $response;
    }

    /**
     * Get CURL infos of last request
     *
     * @return array|null
     */
    public function requestInfos()
    {
        if (is_resource($this->curl)) {
            return curl_getinfo($this->curl);
        } else {
            return null;
        }
    }

    /**
     * Return the HTTP status of last request
     *
     * @return integer
     */
    public function statusCode()
    {
        if (is_array($tmp = $this->requestInfos())) {
            return $tmp['http_code'];
        } else {
            return 0;
        }
    }

    /**
     * Toggle CURL verbose mode
     *
     * @param bool $flag
     */
    public function setCurlVerbose($flag = false)
    {
        $this->curlVerbose = $flag;
        curl_setopt($this->curl, CURLOPT_VERBOSE, $this->curlVerbose);
    }
}

