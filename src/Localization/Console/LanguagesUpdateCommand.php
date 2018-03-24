<?php

namespace Nova\Localization\Console;

use Nova\Console\Command;
use Nova\Filesystem\FileNotFoundException;
use Nova\Filesystem\Filesystem;
use Nova\Localization\LanguageManager;
use Nova\Support\Arr;
use Nova\Support\Str;

use Exception;
use Throwable;


class LanguagesUpdateCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'language:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the Language files';

    /**
     * The Language Manager instance.
     *
     * @var LanguageManager
     */
    protected $languages;

    /**
     * The Filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;


    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     */
    public function __construct(LanguageManager $languages, Filesystem $files)
    {
        parent::__construct();

        //
        $this->languages = $languages;

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $config = $this->container['config'];

        // Get the Language codes.
        $languages = array_keys(
            $config->get('languages', array())
        );

        $workPaths = array(
            app_path(),
            base_path('shared')
        );

        // Search for the Modules and Themes.
        $paths = array(
            $config->get('packages.modules.path', BASEPATH .'modules'),
            $config->get('packages.themes.path', BASEPATH .'themes')
        );

        foreach ($paths as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            }

            $workPaths = array_merge(
                $workPaths, $this->files->glob($path .'/*', GLOB_ONLYDIR)
            );
        }

        // Search for the local Packages.
        $path = BASEPATH .'packages';

        if ($this->files->isDirectory($path)) {
            $workPaths = array_merge($workPaths, array_map(function ($path)
            {
                return $path .DS .'src';

            }, $this->files->glob($path .'/*', GLOB_ONLYDIR)));
        }

        // Update the Language files in the available Domains.
        foreach($workPaths as $path) {
            if ($this->files->isDirectory($path)) {
                $this->updateLanguageFiles($path, $languages);
            }
        }
    }

    protected function updateLanguageFiles($path, $languages)
    {
        $insideApp = ($path == app_path());

        $pattern = $insideApp ? "__('" : "__d('";

        if (empty($paths = $this->fileGrep($pattern, $path))) {
            $this->comment(PHP_EOL .'No messages found in path: "' .$path .'"');

            return;
        }

        // Extract the messages from files.
        $messages = $this->extractMessages($paths, $insideApp);

        if (! empty($messages)) {
            $this->info(PHP_EOL .'Processing the messages found in path: "' .$path .'"');

            foreach($languages as $language) {
                $this->updateLanguage($language, $path, $messages);
            }
        }
    }

    protected function extractMessages(array $paths, $insideApp)
    {
        if ($insideApp) {
            $pattern = '#__\(\'(.*)\'(?:,.*)?\)#smU';
        } else {
            $pattern = '#__d\(\'(?:.*)?\',.?\s?\'(.*)\'(?:,.*)?\)#smU';
        }

        //$this->comment("Using PATERN: '" .$pattern."'");

        // Extract the messages from files and return them.
        $result = array();

        foreach($paths as $path) {
            $content = $this->getFileContents($path);

            if (preg_match_all($pattern, $content, $matches) !== false) {
                $messages = $matches[1];

                foreach ($messages as $message) {
                    //$message = trim($message);

                    if ($message == '$msg, $args = null') {
                        // We will skip the functions definition.
                        continue;
                    }

                    $key = str_replace("\\'", "'", $message);

                    $result[$key] = '';
                }
            }
        }

        return $result;
    }

    protected function getFileContents($path)
    {
        try {
            return $this->files->get($path);
        }
        catch (Exception $e) {
            //
        }
        catch (Throwable $e) {
            //
        }

        return '';
    }

    protected function updateLanguage($language, $path, array $messages)
    {
        $path = str_replace('/', DS, $path .'/Language/' .strtoupper($language) .'/messages.php');

        try {
            $data = $this->files->getRequire($path);
        }
        catch (Exception $e) {
            $data = array();
        }
        catch (Throwable $e) {
            $data = array();
        }

        if (! is_array($data)) {
            $data = array($data);
        }

        foreach($messages as $key => $value) {
            $value = Arr::get($data, $key, '');

            if (is_string($value) && ! empty($value)) {
                $messages[$key] = $value;
            } else {
                $messages[$key] = '';
            }
        }

        ksort($messages);

        $output = "<?php

return " .var_export($messages, true) .";\n";

        //$output = preg_replace("/^ {2}(.*)$/m","    $1", $output);

        $this->files->makeDirectory(dirname($path), 0755, true, true);

        $this->files->put($path, $output);

        $this->line('Written the Language file: "'.str_replace(BASEPATH, '', $path).'"');
    }

    protected function fileGrep($pattern, $path) {
        $result = array();

        $fp = opendir($path);

        while($f = readdir($fp)) {
            if (preg_match("#^\.+$#", $f) === 1) continue; // ignore symbolic links

            $fullPath = $path .DS .$f;

            if ($this->files->isDirectory($fullPath)) {
                $result = array_unique(array_merge($result, $this->fileGrep($pattern, $fullPath)));
            }
            else if(stristr(file_get_contents($fullPath), $pattern)) {
                $result[] = $fullPath;
            }
        }

        return $result;
    }
}
