<?php

namespace Nova\Cache;

use Nova\Cache\Contracts\StoreInterface;
use Nova\Support\Manager;

use Closure;


class CacheManager extends Manager
{
	/**
	 * Create an instance of the APC cache driver.
	 *
	 * @return \Nova\Cache\ApcStore
	 */
	protected function createApcDriver()
	{
		return $this->repository(new ApcStore(new ApcWrapper, $this->getPrefix()));
	}

	/**
	 * Create an instance of the array cache driver.
	 *
	 * @return \Nova\Cache\ArrayStore
	 */
	protected function createArrayDriver()
	{
		return $this->repository(new ArrayStore);
	}

	/**
	 * Create an instance of the file cache driver.
	 *
	 * @return \Nova\Cache\FileStore
	 */
	protected function createFileDriver()
	{
		$path = $this->app['config']['cache.path'];

		return $this->repository(new FileStore($this->app['files'], $path));
	}

	/**
	 * Create an instance of the Memcached cache driver.
	 *
	 * @return \Nova\Cache\MemcachedStore
	 */
	protected function createMemcachedDriver()
	{
		$servers = $this->app['config']['cache.memcached'];

		$memcached = $this->app['memcached.connector']->connect($servers);

		return $this->repository(new MemcachedStore($memcached, $this->getPrefix()));
	}

	/**
	 * Create an instance of the Null cache driver.
	 *
	 * @return \Nova\Cache\NullStore
	 */
	protected function createNullDriver()
	{
		return $this->repository(new NullStore);
	}

	/**
	 * Create an instance of the WinCache cache driver.
	 *
	 * @return \Nova\Cache\WinCacheStore
	 */
	protected function createWincacheDriver()
	{
		return $this->repository(new WinCacheStore($this->getPrefix()));
	}

	/**
	 * Create an instance of the XCache cache driver.
	 *
	 * @return \Nova\Cache\WinCacheStore
	 */
	protected function createXcacheDriver()
	{
		return $this->repository(new XCacheStore($this->getPrefix()));
	}

	/**
	 * Create an instance of the Redis cache driver.
	 *
	 * @return \Nova\Cache\RedisStore
	 */
	protected function createRedisDriver()
	{
		$redis = $this->app['redis'];

		return $this->repository(new RedisStore($redis, $this->getPrefix()));
	}

	/**
	 * Create an instance of the database cache driver.
	 *
	 * @return \Nova\Cache\DatabaseStore
	 */
	protected function createDatabaseDriver()
	{
		$connection = $this->getDatabaseConnection();

		$encrypter = $this->app['encrypter'];

		// We allow the developer to specify which connection and table should be used
		// to store the cached items. We also need to grab a prefix in case a table
		// is being used by multiple applications although this is very unlikely.
		$table = $this->app['config']['cache.table'];

		$prefix = $this->getPrefix();

		return $this->repository(new DatabaseStore($connection, $encrypter, $table, $prefix));
	}

	/**
	 * Get the database connection for the database driver.
	 *
	 * @return \Nova\Database\Connection
	 */
	protected function getDatabaseConnection()
	{
		$connection = $this->app['config']['cache.connection'];

		return $this->app['db']->connection($connection);
	}

	/**
	 * Get the cache "prefix" value.
	 *
	 * @return string
	 */
	public function getPrefix()
	{
		return $this->app['config']['cache.prefix'];
	}

	/**
	 * Set the cache "prefix" value.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public function setPrefix($name)
	{
		$this->app['config']['cache.prefix'] = $name;
	}

	/**
	 * Create a new cache repository with the given implementation.
	 *
	 * @param  \Nova\Cache\Contracts\StoreInterface  $store
	 * @return \Nova\Cache\Repository
	 */
	protected function repository(StoreInterface $store)
	{
		return new Repository($store);
	}

	/**
	 * Get the default cache driver name.
	 *
	 * @return string
	 */
	public function getDefaultDriver()
	{
		return $this->app['config']['cache.driver'];
	}

	/**
	 * Set the default cache driver name.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public function setDefaultDriver($name)
	{
		$this->app['config']['cache.driver'] = $name;
	}

}
