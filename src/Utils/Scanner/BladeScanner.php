<?php
namespace PathMotion\CI\Utils\Scanner;

use Gettext\Translations;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Philo\Blade\Blade;

class BladeScanner extends PhpScanner
{

    public function __construct(Translations ...$allTranslations)
    {
        parent::__construct(...$allTranslations);
        $this->functions['t'] = 'gettext';
        $cache = __DIR__ . '/cache';

        $fs = new Filesystem();
        $this->blade = new BladeCompiler($fs, $cache);
    }

    /**
     * Scan php blade template
     *
     * @param string $string
     * @param string $filename
     * @return void
     */
    public function scanString(string $string, string $filename): void
    {
        $string = $this->blade->compileString($string);
        $functionsScanner = $this->getFunctionsScanner();
        $functions = $functionsScanner->scan($string, $filename);

        foreach ($functions as $function) {
            $this->handleFunction($function);
        }
    }
}