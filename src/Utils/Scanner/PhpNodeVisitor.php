<?php
namespace PathMotion\CI\Utils\Scanner;

use Gettext\Scanner\ParsedFunction;
use Gettext\Scanner\PhpNodeVisitor as ScannerPhpNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

/**
 * Will help to extract PHP code
 */
class PhpNodeVisitor extends ScannerPhpNodeVisitor
{

    /**
     * Enter node override to handle method calls
     * @param Node $node
     * @return null
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof MethodCall) {
            $name = ($node->name instanceof Identifier) ? (string)$node->name : null;

            if ($name && ($this->validFunctions === null || in_array($name, $this->validFunctions))) {
                $this->functions[] = $this->createMethod($node);
            }
            return null;
        }
        return parent::enterNode($node);
    }

    /**
     * Create method is an adaptation of the createFunction method
     * @param MethodCall $node
     * @return ParsedFunction
     */
    protected function createMethod(MethodCall $node): ParsedFunction
    {
        $function = new ParsedFunction(
            (string)$node->name,
            $this->filename,
            $node->getStartLine(),
            $node->getEndLine()
        );

        foreach ($node->getComments() as $comment) {
            $function->addComment(static::getComment($comment));
        }

        foreach ($this->bufferComments as $comment) {
            $function->addComment(static::getComment($comment));
        }

        foreach ($node->args as $argument) {
            $value = $argument->value;

            foreach ($argument->getComments() as $comment) {
                $function->addComment(static::getComment($comment));
            }

            $function->addArgument(static::getValue($value));
        }

        return $function;
    }
}
