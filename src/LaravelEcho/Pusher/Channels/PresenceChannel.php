<?php

namespace BeyondCode\LaravelWebSockets\LaravelEcho\Pusher\Channels;

use Ratchet\ConnectionInterface;

class PresenceChannel extends Channel
{
    protected $subscriptions = [];

    /*
     * @link https://pusher.com/docs/pusher_protocol#presence-channel-events
     */
    public function subscribe(ConnectionInterface $connection, $payload)
    {
        $this->saveConnection($connection);

        $channelData = json_decode($payload->channel_data);
        $this->subscriptions[$connection->socketId] = $channelData;

        // Send the success event
        $connection->send(json_encode([
            'event' => 'pusher_internal:subscription_succeeded',
            'channel' => $this->channelId,
            'data' => json_encode($this->getChannelData())
        ]));

        $this->broadcastToOthers($connection, [
            'event' => 'pusher_internal:member_added',
            'channel' => $this->channelId,
            'data' => json_encode($channelData)
        ]);
    }

    public function unsubscribe(ConnectionInterface $connection)
    {
        parent::unsubscribe($connection);

        $this->broadcastToOthers($connection, [
            'event' => 'pusher_internal:member_removed',
            'channel' => $this->channelId,
            'data' => json_encode([
                'user_id' => $this->subscriptions[$connection->socketId]->user_id
            ])
        ]);

        unset($this->subscriptions[$connection->socketId]);
    }

    protected function getChannelData(): array
    {
        return [
            'presence' => [
                'ids' => array_map(function($channelData) { return $channelData->user_id; }, $this->subscriptions),
                'hash' => array_map(function($channelData) { return $channelData->user_info; }, $this->subscriptions),
                'count' => count($this->subscriptions)
            ]
        ];
    }
}