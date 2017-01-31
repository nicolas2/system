<?php

namespace Nova\Module;

use Nova\Helpers\Inflector;
use Nova\Foundation\Application;
use Nova\Module\RepositoryInterface;


class ModuleRepository implements RepositoryInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * Create a new ModuleRepository instance.
     *
     * @param Application         $app
     * @param RepositoryInterface $repository
     */
    public function __construct(Application $app, RepositoryInterface $repository)
    {
        $this->app = $app;

        $this->repository = $repository;
    }

    /**
     * Register the module service provider file from all modules.
     *
     * @return mixed
     */
    public function register()
    {
        $modules = $this->repository->enabled();

        $modules->each(function ($properties) {
            $this->registerServiceProvider($properties);

            $this->registerWidgetsNamespace($properties);

            $this->autoloadFiles($properties);
        });
    }

    /**
     * Register the Module Service Provider.
     *
     * @param string $properties
     *
     * @return string
     *
     * @throws \Nova\Module\FileMissingException
     */
    protected function registerServiceProvider($properties)
    {
        $namespace = $this->resolveNamespace($properties);

        $file = $this->repository->getPath() .DS .$namespace .DS .'Providers' .DS .$namespace .'ServiceProvider.php';

        $serviceProvider = $this->repository->getNamespace() ."\\{$namespace}\\Providers\\{$namespace}ServiceProvider";

        if (class_exists($serviceProvider)) {
            $this->app->register($serviceProvider);
        }
    }

    /**
     * Register the Module Service Provider.
     *
     * @param string $properties
     *
     * @return string
     *
     * @throws \Nova\Module\FileMissingException
     */
    protected function registerWidgetsNamespace($properties)
    {
        $widgets = $this->app['widgets'];

        //
        $namespace = $this->resolveNamespace($properties);

        $namespace = $this->repository->getNamespace() .'\\{$namespace}\\Widgets';

        $widgets->register($namespace);
    }

    /**
     * Autoload custom module files.
     *
     * @param array $properties
     */
    protected function autoloadFiles($properties)
    {
        if (! isset($properties['autoload'])) {
            $files = array('Config.php', 'Events.php', 'Filters.php', 'Routes.php', 'Bootstrap.php');
        } else {
            $files = $properties['autoload'];
        }

        $namespace = $this->resolveNamespace($properties);

        //
        $basePath = $this->repository->getPath() .DS .$namespace .DS;

        foreach ($files as $file) {
            $path = $basePath .$file;

            if (is_readable($path)) require $path;
        }
    }

    public function optimize()
    {
        return $this->repository->optimize();
    }

    /**
     * Get all modules.
     *
     * @return Collection
     */
    public function all()
    {
        return $this->repository->all();
    }

    /**
     * Get all module slugs.
     *
     * @return array
     */
    public function slugs()
    {
        return $this->repository->slugs();
    }

    /**
     * Get modules based on where clause.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return Collection
     */
    public function where($key, $value)
    {
        return $this->repository->where($key, $value);
    }

    /**
     * Sort modules by given key in ascending order.
     *
     * @param string $key
     *
     * @return Collection
     */
    public function sortBy($key)
    {
        return $this->repository->sortBy($key);
    }

    /**
     * Sort modules by given key in ascending order.
     *
     * @param string $key
     *
     * @return Collection
     */
    public function sortByDesc($key)
    {
        return $this->repository->sortByDesc($key);
    }

    /**
     * Check if the given module exists.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function exists($slug)
    {
        return $this->repository->exists($slug);
    }

    /**
     * Returns count of all modules.
     *
     * @return int
     */
    public function count()
    {
        return $this->repository->count();
    }

    /**
     * Get modules path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->repository->getPath();
    }

    /**
     * Set modules path in "RunTime" mode.
     *
     * @param string $path
     *
     * @return object $this
     */
    public function setPath($path)
    {
        return $this->repository->setPath($path);
    }

    /**
     * Get path for the specified module.
     *
     * @param string $slug
     *
     * @return string
     */
    public function getModulePath($slug)
    {
        return $this->repository->getModulePath($slug);
    }

    /**
     * Get modules namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->repository->getNamespace();
    }

    /**
     * Get a module's properties.
     *
     * @param string $slug
     *
     * @return mixed
     */
    public function getManifest($slug)
    {
        return $this->repository->getManifest($slug);
    }

    /**
     * Get a module property value.
     *
     * @param string $property
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($property, $default = null)
    {
        return $this->repository->get($property, $default);
    }

    /**
     * Set a module property value.
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return bool
     */
    public function set($property, $value)
    {
        return $this->repository->set($property, $value);
    }

    /**
     * Gets all enabled modules.
     *
     * @return array
     */
    public function enabled()
    {
        return $this->repository->enabled();
    }

    /**
     * Gets all disabled modules.
     *
     * @return array
     */
    public function disabled()
    {
        return $this->repository->disabled();
    }

    /**
     * Check if specified module is enabled.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function isEnabled($slug)
    {
        return $this->repository->isEnabled($slug);
    }

    /**
     * Check if specified module is disabled.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function isDisabled($slug)
    {
        return $this->repository->isDisabled($slug);
    }

    /**
     * Enables the specified module.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function enable($slug)
    {
        return $this->repository->enable($slug);
    }

    /**
     * Disables the specified module.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function disable($slug)
    {
        return $this->repository->disable($slug);
    }

    /**
     * Resolve the correct module namespace.
     *
     * @param array $properties
     */
    public function resolveNamespace($properties)
    {
        if (isset($properties['namespace'])) return $properties['namespace'];

        return Inflector::classify($properties['slug']);
    }
}
