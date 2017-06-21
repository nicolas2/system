<?php

namespace Nova\Broadcasting\Broadcasters;

use Nova\Broadcasting\Broadcaster;

use Psr\Log\LoggerInterface;


class LogBroadcaster extends Broadcaster
{
	/**
	 * The logger implementation.
	 *
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * Create a new broadcaster instance.
	 *
	 * @param  \Psr\Log\LoggerInterface  $logger
	 * @return void
	 */
	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function authenticate($request)
	{
		//
	}

	/**
	 * {@inheritdoc}
	 */
	public function validAuthenticationResponse($request, $result)
	{
		//
	}

	/**
	 * {@inheritdoc}
	 */
	public function broadcast(array $channels, $event, array $payload = array())
	{
		$channels = implode(', ', $channels);

		$payload = json_encode($payload, JSON_PRETTY_PRINT);

		$this->logger->info('Broadcasting [' .$event .'] on channels [' .$channels .'] with payload:' .PHP_EOL .$payload);
	}
}