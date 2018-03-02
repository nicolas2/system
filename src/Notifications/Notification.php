<?php

namespace Nova\Notifications;

use Nova\Queue\SerializesModelsTrait;


class Notification
{
    use SerializesModelsTrait;

    /**
     * The unique identifier for the notification.
     *
     * @var string
     */
    public $id;


    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return array();
    }
}
