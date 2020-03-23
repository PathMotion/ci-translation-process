<?php
namespace PathMotion\CI\Utils\Scanner;

use Gettext\Scanner\CodeScanner;
use Gettext\Scanner\FunctionsScannerInterface;
use Gettext\Translations;

class PhpScanner extends CodeScanner
{
    /**
     * Add translation after constructor
     * @param Translations $translations
     * @return self
     */
    public function addTranslations(Translations $translations): self
    {
        $domain = $translations->getDomain();
        $this->translations[$domain] = $translations;
        return $this;
    }

    /**
     * Get custom `PhpFunctionsScanner` instance
     * @return FunctionsScannerInterface
     */
    public function getFunctionsScanner(): FunctionsScannerInterface
    {
        return new PhpFunctionsScanner(array_keys($this->functions));
    }

    /**
     * Count all translations
     * @return integer
     */
    public function getTranslationCount(): int
    {
        $total = 0;

        foreach ($this->getTranslations() as $domainTranslation) {
            $total += $domainTranslation->count();
        }
        return $total;
    }
}
