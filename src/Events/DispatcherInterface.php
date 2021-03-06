<?php

namespace Nova\Events;


interface DispatcherInterface
{
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  string|array  $events
     * @param  mixed  $listener
     * @param  int  $priority
     * @return void
     */
    public function listen($events, $listener, $priority = 0);

    /**
     * Determine if a given event has listeners.
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners($eventName);

    /**
     * Register an event and payload to be fired later.s
     *
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function push($event, $payload = array());

    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param  object|string  $subscriber
     * @return void
     */
    public function subscribe($subscriber);

    /**
     * Fire an event until the first non-null response is returned.
     *
     * @param  string  $event
     * @param  array  $payload
     * @return mixed
     */
    public function until($event, $payload = array());

    /**
     * Flush a set of pushed events.
     *
     * @param  string  $event
     * @return void
     */
    public function flush($event);

    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function dispatch($event, $payload = array(), $halt = false);

    /**
     * Get the event that is currently firing.
     *
     * @return string
     */
    public function dispatching();

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string  $event
     * @return void
     */
    public function forget($event);

    /**
     * Forget all of the queued listeners.
     *
     * @return void
     */
    public function forgetPushed();
}
