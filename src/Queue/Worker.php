<?php

namespace Nova\Queue;

use Nova\Events\Dispatcher;
use Nova\Queue\Jobs\Job;
use Nova\Cache\Repository as CacheRepository;
use Nova\Queue\Failed\FailedJobProviderInterface;


class Worker
{

    /**
     * The queue manager instance.
     *
     * @var \Nova\Queue\QueueManager
     */
    protected $manager;

    /**
     * The failed job provider implementation.
     *
     * @var \Nova\Queue\Failed\FailedJobProviderInterface
     */
    protected $failer;

    /**
     * The event dispatcher instance.
     *
     * @var \Nova\Events\Dispatcher
     */
    protected $events;

    /**
     * The cache repository implementation.
     *
     * @var \Nova\Cache\Repository
     */
    protected $cache;

    /**
     * The exception handler instance.
     *
     * @var \Nova\Exception\Handler
     */
    protected $exceptions;

    /**
     * Create a new queue worker.
     *
     * @param  \Nova\Queue\QueueManager  $manager
     * @param  \Nova\Queue\Failed\FailedJobProviderInterface  $failer
     * @param  \Nova\Events\Dispatcher  $events
     * @return void
     */
    public function __construct(QueueManager $manager,
                                FailedJobProviderInterface $failer = null,
                                Dispatcher $events = null)
    {
        $this->failer = $failer;
        $this->events = $events;
        $this->manager = $manager;
    }

    /**
     * Listen to the given queue in a loop.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @param  int     $delay
     * @param  int     $memory
     * @param  int     $sleep
     * @param  int     $maxTries
     * @return array
     */
    public function daemon($connectionName, $queue = null, $delay = 0, $memory = 128, $sleep = 3, $maxTries = 0)
    {
        $lastRestart = $this->getTimestampOfLastQueueRestart();

        while (true) {
            if ($this->daemonShouldRun()) {
                $this->runNextJobForDaemon(
                    $connectionName, $queue, $delay, $sleep, $maxTries
                );
            } else {
                $this->sleep($sleep);
            }

            if ($this->memoryExceeded($memory) || $this->queueShouldRestart($lastRestart)) {
                $this->stop();
            }
        }
    }

    /**
     * Run the next job for the daemon worker.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @param  int  $delay
     * @param  int  $sleep
     * @param  int  $maxTries
     * @return void
     */
    protected function runNextJobForDaemon($connectionName, $queue, $delay, $sleep, $maxTries)
    {
        try
        {
            $this->pop($connectionName, $queue, $delay, $sleep, $maxTries);
        }
        catch (\Exception $e) {
            if ($this->exceptions) $this->exceptions->handleException($e);
        }
        catch (\Throwable $e) {
            if ($this->exceptions) $this->exceptions->handleException($e);
        }
    }

    /**
     * Determine if the daemon should process on this iteration.
     *
     * @return bool
     */
    protected function daemonShouldRun()
    {
        if ($this->manager->isDownForMaintenance()) {
            return false;
        }

        return $this->events->until('nova.queue.looping') !== false;
    }

    /**
     * Listen to the given queue.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @param  int     $delay
     * @param  int     $sleep
     * @param  int     $maxTries
     * @return array
     */
    public function pop($connectionName, $queue = null, $delay = 0, $sleep = 3, $maxTries = 0)
    {
        $connection = $this->manager->connection($connectionName);

        $job = $this->getNextJob($connection, $queue);

        // If we're able to pull a job off of the stack, we will process it and
        // then immediately return back out. If there is no job on the queue
        // we will "sleep" the worker for the specified number of seconds.
        if ( ! is_null($job)) {
            return $this->process(
                $this->manager->getName($connectionName), $job, $maxTries, $delay
            );
        }

        $this->sleep($sleep);

        return array('job' => null, 'failed' => false);
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param  \Nova\Queue\Queue  $connection
     * @param  string  $queue
     * @return \Nova\Queue\Jobs\Job|null
     */
    protected function getNextJob($connection, $queue)
    {
        if (is_null($queue)) return $connection->pop();

        foreach (explode(',', $queue) as $queue) {
            if ( ! is_null($job = $connection->pop($queue))) return $job;
        }
    }

    /**
     * Process a given job from the queue.
     *
     * @param  string  $connection
     * @param  \Nova\Queue\Jobs\Job  $job
     * @param  int  $maxTries
     * @param  int  $delay
     * @return void
     *
     * @throws \Exception
     */
    public function process($connection, Job $job, $maxTries = 0, $delay = 0)
    {
        if ($maxTries > 0 && $job->attempts() > $maxTries) {
            return $this->logFailedJob($connection, $job);
        }

        try
        {
            // First we will fire off the job. Once it is done we will see if it will
            // be auto-deleted after processing and if so we will go ahead and run
            // the delete method on the job. Otherwise we will just keep moving.
            $job->fire();

            if ($job->autoDelete()) $job->delete();

            return ['job' => $job, 'failed' => false];
        }

        catch (\Exception $e) {
            // If we catch an exception, we will attempt to release the job back onto
            // the queue so it is not lost. This will let is be retried at a later
            // time by another listener (or the same one). We will do that here.
            if ( ! $job->isDeleted()) $job->release($delay);

            throw $e;
        }
        catch (\Throwable $e)
        {
            if ( ! $job->isDeleted()) $job->release($delay);

            throw $e;
        }
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  \Nova\Queue\Jobs\Job  $job
     * @return array
     */
    protected function logFailedJob($connection, Job $job)
    {
        if ($this->failer) {
            $this->failer->log($connection, $job->getQueue(), $job->getRawBody());

            $job->delete();

            $this->raiseFailedJobEvent($connection, $job);
        }

        return ['job' => $job, 'failed' => true];
    }

    /**
     * Raise the failed queue job event.
     *
     * @param  string  $connection
     * @param  \Nova\Queue\Jobs\Job  $job
     * @return void
     */
    protected function raiseFailedJobEvent($connection, Job $job)
    {
        if ($this->events) {
            $data = json_decode($job->getRawBody(), true);

            $this->events->fire('Nova.queue.failed', array($connection, $job, $data));
        }
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param  int   $memoryLimit
     * @return bool
     */
    public function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @return void
     */
    public function stop()
    {
        $this->events->fire('nova.queue.stopping');

        die;
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param  int   $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        sleep($seconds);
    }

    /**
     * Get the last queue restart timestamp, or null.
     *
     * @return int|null
     */
    protected function getTimestampOfLastQueueRestart()
    {
        if ($this->cache) {
            return $this->cache->get('nova:queue:restart');
        }
    }

    /**
     * Determine if the queue worker should restart.
     *
     * @param  int|null  $lastRestart
     * @return bool
     */
    protected function queueShouldRestart($lastRestart)
    {
        return $this->getTimestampOfLastQueueRestart() != $lastRestart;
    }

    /**
     * Set the exception handler to use in Daemon mode.
     *
     * @param  \Nova\Exception\Handler  $handler
     * @return void
     */
    public function setDaemonExceptionHandler($handler)
    {
        $this->exceptions = $handler;
    }

    /**
     * Set the cache repository implementation.
     *
     * @param  \Nova\Cache\Repository  $cache
     * @return void
     */
    public function setCache(CacheRepository $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Get the queue manager instance.
     *
     * @return \Nova\Queue\QueueManager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Set the queue manager instance.
     *
     * @param  \Nova\Queue\QueueManager  $manager
     * @return void
     */
    public function setManager(QueueManager $manager)
    {
        $this->manager = $manager;
    }

}
