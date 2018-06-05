<?php

namespace Nova\Broadcasting\Channels;

use Nova\Broadcasting\Channels\PublicChannel;


class PrivateChannel extends PublicChannel
{
    /**
     * Create a new channel instance.
     *
     * @param  string  $name
     * @return void
     */
    public function __construct($name)
    {
        parent::__construct('private-' .$name);
    }
}