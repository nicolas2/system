<?php

namespace Nova\Assets;

use Nova\Support\Arr;

use BadMethodCallException;
use InvalidArgumentException;


class AssetManager
{
    /**
     * The Assets Positions
     *
     * @var array
     */
    protected $positions = array(
        'css' => array(),
        'js'  => array(),
    );

    /**
     *  The standard Asset Templates
     *
     * @var array
     */
    protected static $templates = array(
        'css' => '<link href="%s" rel="stylesheet" type="text/css">',
        'js'  => '<script src="%s" type="text/javascript"></script>',
    );

    /**
     *  The inline Asset Templates
     *
     * @var array
     */
    protected static $inlineTemplates = array(
        'css' => '<style>%s</style>',
        'js'  => '<script type="text/javascript">%s</script>',
    );

    /**
     * Register new Assets.
     *
     * @param  string $type
     * @param  string|array $assets
     * @param  string|null $position
     * @param  int $order
     * @param  string $mode
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function register($type, $assets, $position = 'header', $order = 0, $mode = 'default')
    {
        if (! in_array($type, $this->getTypes())) {
            throw new InvalidArgumentException("Invalid assets type [${type}]");
        } else if (! in_array($mode, array('default', 'inline'))) {
            throw new InvalidArgumentException("Invalid assets mode [${mode}]");
        }

        // The assets type and mode are valid.
        else if (empty($items = $this->parseAssets($assets))) {
            return;
        }

        // Check the assets position setup.
        else if (! Arr::has($this->positions[$type], $position)) {
            $this->positions[$type][$position] = array();
        }

        foreach ($items as $content) {
            $this->positions[$type][$position][] = compact('content', 'order', 'mode');
        }
    }

    /**
     * Render the Assets for implicit or a specified position.
     *
     * @param  string $type
     * @param  string $position
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function render($type, $position = 'header')
    {
        if (! in_array($type, $this->getTypes())) {
            throw new InvalidArgumentException("Invalid assets type [${type}]");
        }

        // The assets type is valid.
        else if (empty($items = Arr::get($this->positions[$type], $position, array()))) {
            return;
        }

        usort($items, function ($a, $b)
        {
            if ($a['order'] == $b['order']) return 0;

            return ($a['order'] > $b['order']) ? 1 : -1;
        });

        return implode("\n", array_map(function ($item) use ($type)
        {
            $mode = Arr::get($item, 'mode');

            if ($mode === 'inline') {
                $template = Arr::get(static::$inlineTemplates, $type);
            } else {
                $template = Arr::get(static::$templates, $type);
            }

            return sprintf($template, Arr::get($item, 'content'));

        }, $items));
    }

    /**
     * Build the CSS or JS scripts.
     *
     * @param string       $type
     * @param string|array $files
     *
     * @return string|null
     * @throws \InvalidArgumentException
     */
    public function build($type, $assets)
    {
        if (! in_array($type, $this->getTypes())) {
            throw new InvalidArgumentException("Invalid assets type [${type}]");
        }

        // The assets type is valid.
        else if (empty($items = $this->parseAssets($assets))) {
            return;
        }

        $template = Arr::get(static::$templates, $type);

        return implode("\n", array_map(function ($content) use ($template)
        {
            return sprintf($template, $content);

        }, $items));
    }

    /**
     * Parses and returns the given assets.
     *
     * @param  string|array $assets
     *
     * @return array
     */
    protected function parseAssets($assets)
    {
        if (is_string($assets) && ! empty($assets)) {
            $assets = array($assets);
        } else if (! is_array($assets)) {
            return array();
        }

        return array_filter($assets, function ($value)
        {
            return ! empty($value);
        });
    }

    /**
     * Returns the known Asset Types.
     *
     * @return array
     */
    protected function getTypes()
    {
        return array_keys(static::$templates);
    }
}
