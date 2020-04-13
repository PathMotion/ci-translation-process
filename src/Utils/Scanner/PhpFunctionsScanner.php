<?php
namespace PathMotion\CI\Utils\Scanner;

use PhpParser\NodeVisitor;
use Gettext\Scanner\PhpFunctionsScanner as PhpFunctionsScannerOriginal;

/**
 * Override Gettext\Scanner\PhpFunctionsScanner
 * To add custom PhpNodeVisitor
 */
class PhpFunctionsScanner extends PhpFunctionsScannerOriginal
{
    /**
     * Create node visitor
     *
     * @param string $filename
     * @return NodeVisitor
     */
    protected function createNodeVisitor(string $filename): NodeVisitor
    {
        return new PhpNodeVisitor($filename, $this->validFunctions);
    }
}
