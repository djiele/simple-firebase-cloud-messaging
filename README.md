# Firebase Cloud Messaging Library
Another FCM (Firebase Cloud Messaging) library, light and easy to use. 

Note: this library wrap the legacy Firebase HTTP API, i don't know when it will become obsolete.

##### Installation

You can install the package via composer:

```
composer require djiele/simple-fire-cloud-messaging "dev-master"
```

##### Simple usage


```php
require_once __DIR__'./vendor/autoload.php';
use Djiele\Fcm\WebPushNotifications;
$notifier = new WebPushNotifications('<YOUR_SERVER_KEY>', '<YOUR_SENDER_ID>');
$notifier->batchSubscribeTopic('<YOUR_TOPIC>', ['<DEVICE_TOKEN1>', '<DEVICE_TOKEN2>']);
$response = $notifier->sendToTopic('<YOUR_TOPIC>', 'What time is it?', 'The time is ' . date('H:i:s'), ['lottery' => (0 == rand(0, 100)%2)]);
print_r($response);
```

##### Features
Subscribe one or more devices to a given topic
```php
$response = $notifier->batchSubscribeTopic('<YOUR_TOPIC>', ['<DEVICE_TOKEN1>', '<DEVICE_TOKEN2>']);
print_r($response);```
```
Unsubscribe one or more devices to a given topic
```php
$response = $notifier->batchUnsubscribeTopic('<YOUR_TOPIC>', ['<DEVICE_TOKEN1>', '<DEVICE_TOKEN2>']);
print_r($response);
```
Subscribe a single device to a topic
```php
$response = $notifier->subscribeTopic('<YOUR_TOPIC>', '<DEVICE_TOKEN>');
print_r($response);
```
Send notification to a topic
```php
$response = $notifier->sendToTopic('<YOUR_TOPIC>', 'What time is it?', 'The time is ' . date('H:i:s'), ['lottery' => (0 == rand(0, 100)%2)]);
print_r($response);
```


