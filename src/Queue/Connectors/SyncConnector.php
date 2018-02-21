<?php

namespace Nova\Queue\Connectors;

use Nova\Queue\Connector\ConnectorInterface;
use Nova\Queue\Queues\SyncQueue;


class SyncConnector implements ConnectorInterface
{

    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Nova\Queue\Contracts\QueueInterface
     */
    public function connect(array $config)
    {
        return new SyncQueue;
    }

}
