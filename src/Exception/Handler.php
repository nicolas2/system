<?php

namespace Nova\Exception;

use Nova\Exception\PlainDisplayer;
use Nova\Exception\WhoopsDisplayer;
use Nova\Exception\ExceptionDisplayerInterface;

use Nova\Support\Contracts\ResponsePreparerInterface;
use Nova\Support\Facades\Redirect;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\Exception\FatalThrowableError;

use Closure;
use Exception;
use ErrorException;
use ReflectionFunction;


class Handler
{
    /**
     * The response preparer implementation.
     *
     * @var \Nova\Support\Contracts\ResponsePreparerInterface
     */
    protected $responsePreparer;

    /**
     * The plain exception displayer.
     *
     * @var \Nova\Exception\ExceptionDisplayerInterface
     */
    protected $plainDisplayer;

    /**
     * The debug exception displayer.
     *
     * @var \Nova\Exception\ExceptionDisplayerInterface
     */
    protected $debugDisplayer;

    /**
     * Indicates if the application is in debug mode.
     *
     * @var bool
     */
    protected $debug;

    /**
     * All of the register exception handlers.
     *
     * @var array
     */
    protected $handlers = array();

    /**
     * All of the handled error messages.
     *
     * @var array
     */
    protected $handled = array();


    /**
     * Create a new error handler instance.
     *
     * @param  \Nova\Support\Contracts\ResponsePreparerInterface  $responsePreparer
     * @param  \Exception\ExceptionDisplayerInterface  $plainDisplayer
     * @param  \Exception\ExceptionDisplayerInterface  $debugDisplayer
     * @param  bool  $debug
     * @return void
     */
    public function __construct(ResponsePreparerInterface $responsePreparer,
                                ExceptionDisplayerInterface $plainDisplayer,
                                ExceptionDisplayerInterface $debugDisplayer,
                                $debug = true)
    {
        $this->debug = $debug;

        $this->plainDisplayer = $plainDisplayer;
        $this->debugDisplayer = $debugDisplayer;

        $this->responsePreparer = $responsePreparer;
    }

    /**
     * Register the exception / error handlers for the application.
     *
     * @param  string  $environment
     * @return void
     */
    public function register($environment)
    {
        $this->registerErrorHandler();

        $this->registerExceptionHandler();

        if ($environment != 'testing') {
            $this->registerShutdownHandler();
        }
    }

    /**
     * Register the PHP error handler.
     *
     * @return void
     */
    protected function registerErrorHandler()
    {
        set_error_handler(array($this, 'handleError'));
    }

    /**
     * Register the PHP exception handler.
     *
     * @return void
     */
    protected function registerExceptionHandler()
    {
        set_exception_handler(array($this, 'handleUncaughtException'));
    }

    /**
     * Register the PHP shutdown handler.
     *
     * @return void
     */
    protected function registerShutdownHandler()
    {
        register_shutdown_function(array($this, 'handleShutdown'));
    }

    /**
     * Handle a PHP error for the application.
     *
     * @param  int     $level
     * @param  string  $message
     * @param  string  $file
     * @param  int     $line
     * @param  array   $context
     *
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = array())
    {
        if (error_reporting() & $level) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle an exception for the application.
     *
     * @param  \Exception  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleException($exception)
    {
        if (! $exception instanceof Exception) {
            $exception = new FatalThrowableError($exception);
        }

        if (! is_null($response = $this->callCustomHandlers($exception))) {
            return $this->prepareResponse($response);
        }

        return $this->displayException($exception);
    }

    /**
     * Handle an uncaught exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function handleUncaughtException($exception)
    {
        $response = $this->handleException($exception);

        $response->send();
    }

    /**
     * Handle the PHP shutdown event.
     *
     * @return void
     */
    public function handleShutdown()
    {
        if (! is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $response = $this->handleException($this->fatalExceptionFromError($error, 0));

            $response->send();
        }
    }

    /**
     * Determine if the error type is fatal.
     *
     * @param  int   $type
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE));
    }

    /**
     * Create a new fatal exception instance from an error array.
     *
     * @param  array  $error
     * @param  int|null  $traceOffset
     * @return \Symfony\Component\Debug\Exception\FatalErrorException
     */
    protected function fatalExceptionFromError(array $error, $traceOffset = null)
    {
        return new FatalErrorException(
            $error['message'], $error['type'], 0, $error['file'], $error['line'], $traceOffset
        );
    }

    /**
     * Handle a console exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function handleConsole($exception)
    {
        return $this->callCustomHandlers($exception, true);
    }

    /**
     * Handle the given exception.
     *
     * @param  \Exception  $exception
     * @param  bool  $fromConsole
     * @return void
     */
    protected function callCustomHandlers($exception, $fromConsole = false)
    {
        foreach ($this->handlers as $handler) {
            if (! $this->handlesException($handler, $exception)) {
                continue;
            } else if ($exception instanceof HttpExceptionInterface) {
                $code = $exception->getStatusCode();
            } else {
                $code = 500;
            }

            // We will wrap this handler in a try / catch and avoid white screens of death
            // if any exceptions are thrown from a handler itself. This way we will get
            // at least some errors, and avoid errors with no data or not log writes.
            try {
                $response = call_user_func($handler, $exception, $code, $fromConsole);
            }
            catch (\Exception $e) {
                $response = $this->formatException($e);
            }
            catch (\Throwable $e) {
                $response = $this->formatException($e);
            }

            if (isset($response) && ! is_null($response)) {
                return $response;
            }
        }
    }

    /**
     * Display the given exception to the user.
     *
     * @param  \Exception  $exception
     * @return void
     */
    protected function displayException($exception)
    {
        $displayer = $this->debug ? $this->debugDisplayer : $this->plainDisplayer;

        return $displayer->display($exception);
    }

    /**
     * Determine if the given handler handles this exception.
     *
     * @param  \Closure    $handler
     * @param  \Exception  $exception
     * @return bool
     */
    protected function handlesException(Closure $handler, $exception)
    {
        $reflection = new ReflectionFunction($handler);

        return ($reflection->getNumberOfParameters() == 0) || $this->hints($reflection, $exception);
    }

    /**
     * Determine if the given handler type hints the exception.
     *
     * @param  \ReflectionFunction  $reflection
     * @param  \Exception  $exception
     * @return bool
     */
    protected function hints(ReflectionFunction $reflection, $exception)
    {
        $parameters = $reflection->getParameters();

        $expected = array_shift($parameters);

        return ! $expected->getClass() || $expected->getClass()->isInstance($exception);
    }

    /**
     * Format an exception thrown by a handler.
     *
     * @param  \Exception  $e
     * @return string
     */
    protected function formatException(\Exception $e)
    {
        if ($this->debug) {
            $location = $e->getMessage() .' in '.$e->getFile() .':' .$e->getLine();

            return 'Error in exception handler: '.$location;
        }

        return 'Error in exception handler.';
    }

    /**
     * Register an application error handler.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function error(Closure $callback)
    {
        array_unshift($this->handlers, $callback);
    }

    /**
     * Register an application error handler at the bottom of the stack.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function pushError(Closure $callback)
    {
        $this->handlers[] = $callback;
    }

    /**
     * Prepare the given response.
     *
     * @param  mixed  $response
     * @return \Nova\Http\Response
     */
    protected function prepareResponse($response)
    {
        return $this->responsePreparer->prepareResponse($response);
    }

    /**
     * Determine if we are running in the console.
     *
     * @return bool
     */
    public function runningInConsole()
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * Set the debug level for the handler.
     *
     * @param  bool  $debug
     * @return void
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

}
