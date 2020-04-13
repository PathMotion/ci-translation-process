<?php

namespace PathMotion\CI\Command;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * AbstractCommand class should extends all command class
 * It centralize all common command logic and workflow
 */
abstract class AbstractCommand extends Command
{
    /**
     * Command input
     * @var InputInterface
     */
    private $input;

    /**
     * Command output
     * @var OutputInterface
     */
    private $output;

    /**
     * Implements this method has a command entry point
     * @param InputInterface $input
     * @return void
     */
    abstract public function runCommandLogic(InputInterface $input);

    /**
     * Option validation
     * @throws RuntimeException
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input)->setOutput($output);
        $options = $this->getDefinition()->getOptions();

        foreach ($options as $value) {
            if (!$value->isValueRequired()) {
                continue ;
            }
            $optionName = $value->getName();
            if ($input->getOption($optionName) === null) {
                $errorMessage = 'The "--%s" option is required.';
                throw new RuntimeException(sprintf($errorMessage, $optionName));
            }
        }
    }

    /**
     * Get input information for debug purpose
     * @return array
     */
    protected function debugInput(): array
    {
        $options = $this->getInput()->getOptions();
        $args = $this->getInput()->getArguments();
        $env = getenv();

        return [
            'arguments' => $args,
            'options' => $options,
            'env' => $env
        ];
    }

    /**
     * Store input output instance object
     * And call the run method
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->runCommandLogic($input);
    }

    /**
     * Set command input
     * @param  InputInterface  $input  Command input
     * @return  self
     */
    private function setInput(InputInterface $input):self
    {
        $this->input = $input;
        return $this;
    }

    /**
     * Set command output
     * @param  OutputInterface  $output  Command output
     * @return  self
     */
    private function setOutput(OutputInterface $output): self
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Get command input
     * @return  InputInterface
     */
    public function getInput(): InputInterface
    {
        return $this->input;
    }

    /**
     * Get command output
     * @return  OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Use command output to write a verbose message and adds a newline at the end.
     * @param string $message
     * @param string  $style
     * @return void
     */
    public function verboseLn(string $message, string $style = null)
    {
        return $this->writeln($message, $style, OutputInterface::VERBOSITY_VERBOSE);
    }

    /**
     * Output an error message and
     * Terminates execution of the command with status error
     * @param string $errorMessage
     * @return void
     */
    public function fatalError(string $errorMessage)
    {
        $this->writeln($errorMessage, 'error');
        $this->outputDebug();
        exit(1);
    }

    /**
     * Output debug information in a table
     * @return void
     */
    protected function outputDebug()
    {
        $data = $this->debugInput();
        $tableRows = [];
        foreach($data as $type => $rows) {
            if (count($tableRows) > 0) {
                $tableRows[] = new TableSeparator();
            }
            $tableRows[] = [new TableCell($type, ['colspan' => 2])];
            $tableRows[] = new TableSeparator();
            foreach ($rows as $key => $value) {
                $tableRows[] = [$key, var_export($value, true)];
            }
        }
        $output = $this->getOutput();
        $previousVerbosity = $output->getVerbosity();
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $table = new Table($output);
        $table
            ->setHeaders(['Key', 'Value'])
            ->setRows($tableRows);

        $table->render();
        $output->setVerbosity($previousVerbosity);
    }

    /**
     * Use command output to write a message and adds a newline at the end.
     * You can use `$style` variable to design this output (e.g error will format the message in red)
     *
     * @param string  $message
     * @param string  $style
     * @param integer $options  A bit mask of options (one of the OUTPUT or VERBOSITY constants),
     * 0 is considered the same as OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_NORMAL
     * @return void
     */
    public function writeln(string $message, string $style = null, int $options = 0)
    {
        if (isset($style)) {
            $message = sprintf('<%s>%s</%s>', $style, $message, $style);
        }
        $this->getOutput()->writeln($message, $options);
        return $this;
    }
}
