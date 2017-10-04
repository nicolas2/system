<?php

namespace Nova\Queue\Jobs;

use Nova\Container\Container;
use Nova\Queue\Job;

use Closure;


class SyncJob extends Job
{

    /**
     * The class name of the job.
     *
     * @var string
     */
    protected $job;

    /**
     * The queue message data.
     *
     * @var string
     */
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param  \Nova\Container\Container  $container
     * @param  string  $job
     * @param  string  $data
     * @return void
     */
    public function __construct(Container $container, $job, $data = '')
    {
        $this->job = $job;
        $this->data = $data;
        $this->container = $container;
    }

    /**
     * Fire the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = json_decode($this->data, true);

        if ($this->job instanceof Closure) {
            call_user_func($this->job, $this, $data);
        } else {
            $this->resolveAndHandle(array('job' => $this->job, 'data' => $data));
        }
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody()
    {
        //
    }

    /**
     * Release the job back into the queue.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0)
    {
        //
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return 1;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return '';
    }

}
