<?php
namespace PathMotion\CI\Utils\Scanner;

use Gettext\Scanner\CodeScanner;
use Gettext\Scanner\FunctionsScannerInterface;
use Gettext\Translations;

class PhpScanner extends CodeScanner
{
    public function addTranslations(Translations $translations): self
    {
        $domain = $translations->getDomain();
        $this->translations[$domain] = $translations;
        return $this;
    }

    public function getFunctionsScanner(): FunctionsScannerInterface
    {
        return new PhpFunctionsScanner(array_keys($this->functions));
    }
}
