<?php

namespace Nova\Broadcasting\Contracts;


interface BroadcasterInterface
{
    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param  \Nova\Http\Request  $request
     * @return mixed
     */
    public function authenticate($request);

    /**
     * Return the valid authentication response.
     *
     * @param  \Nova\Http\Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    public function validAuthenticationResponse($request, $result);

    /**
     * Broadcast the given event.
     *
     * @param  array  $channels
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = array());
}
