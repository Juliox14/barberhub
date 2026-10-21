<?php

namespace App\Support;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class WebPushSender
{
    public function __construct(private ?ClientInterface $client = null) {}

    /**
     * @param  array{title: string, body: string, url: string}  $payload
     */
    public function send(PushSubscription $subscription, array $payload): void
    {
        if (! PushEndpoint::isValid($subscription->endpoint)) {
            $subscription->delete();

            return;
        }

        $webPush = new WebPush(
            auth: [
                'VAPID' => [
                    'subject' => config('webpush.subject'),
                    'publicKey' => config('webpush.public_key'),
                    'privateKey' => config('webpush.private_key'),
                ],
            ],
            defaultOptions: ['TTL' => config('webpush.ttl')],
            client: $this->client ?? new Client([
                'connect_timeout' => config('webpush.connect_timeout'),
                'timeout' => config('webpush.timeout'),
                'allow_redirects' => false,
            ]),
            logger: new NullLogger,
        );

        $report = $webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->p256dh,
                'authToken' => $subscription->auth,
            ]),
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['TTL' => config('webpush.ttl')],
        );

        if ($report->isSubscriptionExpired()) {
            $subscription->delete();

            return;
        }

        if (! $report->isSuccess()) {
            throw new RuntimeException('Web Push delivery failed.');
        }
    }
}
