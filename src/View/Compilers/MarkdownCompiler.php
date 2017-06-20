<?php

namespace Nova\View\Compilers;

use Nova\Support\Arr;
use Nova\Support\Str;
use Nova\View\Compilers\Compiler;
use Nova\View\Compilers\CompilerInterface;

use Parsedown;

use Closure;


class MarkdownCompiler extends Compiler implements CompilerInterface
{
	/**
	 * The file currently being compiled.
	 *
	 * @var string
	 */
	protected $path;


	/**
	 * Compile the view at the given path.
	 *
	 * @param  string  $path
	 * @return void
	 */
	public function compile($path = null)
	{
		if (! is_null($path)) {
			$this->setPath($path);
		}

		$contents = $this->compileString($this->files->get($path));

		if ( ! is_null($this->cachePath)) {
			$this->files->put($this->getCompiledPath($this->getPath()), $contents);
		}
	}

	/**
	 * Get the path currently being compiled.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * Set the path currently being compiled.
	 *
	 * @param  string  $path
	 * @return void
	 */
	public function setPath($path)
	{
		$this->path = $path;
	}

	/**
	 * Compile the given Markdown file contents.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public function compileString($value)
	{
		$parsedown = new Parsedown();

		return $parsedown->text($value);
	}
}
