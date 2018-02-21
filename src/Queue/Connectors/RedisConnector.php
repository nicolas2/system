<?php

namespace Nova\Queue\Connectors;

use Nova\Queue\Connector\ConnectorInterface;
use Nova\Queue\Queues\RedisQueue;
use Nova\Redis\Database;


class RedisConnector implements ConnectorInterface
{

    /**
    * The Redis database instance.
    *
     * @var \Nova\Redis\Database
     */
    protected $redis;

    /**
     * The connection name.
     *
     * @var string
     */
    protected $connection;

    /**
     * Create a new Redis queue connector instance.
     *
     * @param  \Nova\Redis\Database  $redis
     * @param  string|null  $connection
     * @return void
     */
    public function __construct(Database $redis, $connection = null)
    {
        $this->redis = $redis;
        $this->connection = $connection;
    }

    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Nova\Queue\Contracts\QueueInterface
     */
    public function connect(array $config)
    {
        $queue = new RedisQueue(
            $this->redis, $config['queue'], array_get($config, 'connection', $this->connection)
        );

        $queue->setExpire(array_get($config, 'expire', 60));

        return $queue;
    }

}
