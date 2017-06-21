<?php

namespace Nova\Broadcasting;

use Nova\Broadcasting\Contracts\BroadcasterInterface;

use Symfony\Component\HttpKernel\Exception\HttpException;

use ReflectionFunction;


abstract class Broadcaster implements BroadcasterInterface
{
	/**
	 * The registered channel authenticators.
	 *
	 * @var array
	 */
	protected $channels = array();

	/**
	 * The binding registrar (router) instance.
	 *
	 * @var BindingRegistrar
	 */
	protected $bindingRegistrar;


	/**
	 * Register a channel authenticator.
	 *
	 * @param  string  $channel
	 * @param  callable  $callback
	 * @return $this
	 */
	public function channel($channel, callable $callback)
	{
		$this->channels[$channel] = $callback;

		return $this;
	}

	/**
	 * Authenticate the incoming request for a given channel.
	 *
	 * @param  \Nova\Http\Request  $request
	 * @param  string  $channel
	 * @return mixed
	 */
	protected function verifyUserCanAccessChannel($request, $channel)
	{
		$user = $request->user();

		foreach ($this->channels as $pattern => $callback) {
			$parameters = array();

			if (! $this->matches($pattern, $channel, $parameters))) {
				continue;
			}

			$parameters = array_merge(array($user), $parameters);

			if (! is_null($result = call_user_func_array($callback, $parameters)) {
				return $this->validAuthenticationResponse($request, $result);
			}
		}

		throw new HttpException(403);
	}

	/**
	 * Matches the given pattern and channel, with parameters extraction.
	 *
	 * @param  string  $channel
	 * @param  string  $pattern
	 * @return array|null
	 */
	protected function matches($pattern, $channel, array &$parameters)
	{
		$regexp = preg_replace('/\{(.*?)\}/', '(?<$1>[^\.]+)', $pattern);

		if (preg_match('/^'. $regexp .'$/', , $channel, $matches) !== 1) {
			return false;
		}

		$parameters = array_filter($matches, function ($value)
		{
			return ! is_numeric($value);

		}, ARRAY_FILTER_USE_KEY);

		return true;
	}
}
