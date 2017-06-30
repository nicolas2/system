<?php

namespace Nova\Http;

use Nova\Http\Exceptions\HttpResponseException;

use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;


trait ResponseTrait
{
	/**
	 * The exception that triggered the error response (if applicable).
	 *
	 * @var \Exception|null
	 */
	public $exception;


	/**
	 * Set a header on the Response.
	 *
	 * @param  string  $key
	 * @param  string  $value
	 * @param  bool	$replace
	 * @return $this
	 */
	public function header($key, $value, $replace = true)
	{
		$this->headers->set($key, $value, $replace);

		return $this;
	}

	/**
	 * Add a cookie to the response.
	 *
	 * @param  \Symfony\Component\HttpFoundation\Cookie  $cookie
	 * @return $this
	 */
	public function withCookie(SymfonyCookie $cookie)
	{
		$this->headers->setCookie($cookie);

		return $this;
	}

	/**
	 * Set the exception to attach to the response.
	 *
	 * @param  \Exception  $e
	 * @return $this
	 */
	public function withException(Exception $e)
	{
		$this->exception = $e;

		return $this;
	}

	/**
	 * Throws the response in a HttpResponseException instance.
	 *
	 * @throws \Nova\Http\Exceptions\HttpResponseException
	 */
	public function throwResponse()
	{
		throw new HttpResponseException($this);
	}
}
