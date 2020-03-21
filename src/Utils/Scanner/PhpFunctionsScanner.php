<?php
namespace PathMotion\CI\Utils\Scanner;

use PhpParser\NodeVisitor;
use Gettext\Scanner\PhpFunctionsScanner as PhpFunctionsScannerOriginal;

class PhpFunctionsScanner extends PhpFunctionsScannerOriginal
{
    protected function createNodeVisitor(string $filename): NodeVisitor
    {
        return new PhpNodeVisitor($filename, $this->validFunctions);
    }
}
