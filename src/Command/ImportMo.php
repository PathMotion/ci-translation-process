<?php
namespace PathMotion\CI\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PathMotion\CI\PoEditor\Client as PoEditorClient;
use PathMotion\CI\PoEditor\Exception\ApiErrorException;
use PathMotion\CI\PoEditor\Exception\IOException;
use PathMotion\CI\PoEditor\Exception\UnexpectedBodyResponseException;
use Symfony\Component\Console\Command\Command;

class ImportMo extends Command
{
    protected static $defaultName = 'import-mo';

    const _OPTION_OUTPUT_DIR_ = 'output-dir';

    const _OPTION_API_KEY_ENV_ = 'api-key-env-name';

    const _OPTION_PROJECT_ID_ = 'project-id';

    const _DEFAULT_API_KEY_ENV_ = 'PO_EDITOR_API_KEY';

    protected function configure()
    {
        $this->setDescription('Import MO from PoEditor.com account.')
            ->addOption(
                self::_OPTION_OUTPUT_DIR_,
                'o',
                InputOption::VALUE_REQUIRED,
                'output directory'
            )->addOption(
                self::_OPTION_API_KEY_ENV_,
                null,
                InputOption::VALUE_OPTIONAL,
                'Environment variable name that contain PoEditor api key',
                self::_DEFAULT_API_KEY_ENV_
            )->addOption(
                self::_OPTION_PROJECT_ID_,
                'p',
                InputOption::VALUE_REQUIRED,
                'PoEditor project id'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $apiKeyEnv = $input->getOption(self::_OPTION_API_KEY_ENV_);
        $apiKey = getenv($apiKeyEnv);

        if (!is_string($apiKey)) {
            $output->writeln(sprintf('<error>Environment variable `%s` not found</error>', $apiKeyEnv));
            exit(1);
        }
        $poEditorClient = new PoEditorClient($apiKey);

        try {
            $projectId = (int)$input->getOption(self::_OPTION_PROJECT_ID_);
            $project = $poEditorClient->getProject($projectId);
        } catch (ApiErrorException $error) {
            $errorMsg = '<error>Cannot retrieve project %d, unexpected PoEditor response (status code %d)</error>';
            $output->writeln(sprintf($errorMsg, $projectId, $error->getCode()));
            exit(1);
        } catch (UnexpectedBodyResponseException $_) {
            $errorMsg = '<error>Cannot retrieve project %d, Mal formated PoEditor response</error>';
            $output->writeln(sprintf($errorMsg, $projectId));
            exit(1);
        }
        $output->writeln(
            sprintf('Project `%s`(%d) has been correctly retrieve', $project->getName(), $projectId),
            OutputInterface::VERBOSITY_VERBOSE
        );
        $languages = [];
        try {
            $languages = $project->languagesList();
        } catch (ApiErrorException $error) {
            $errorMsg = '<error>Cannot retrieve language list, unexpected PoEditor response (status code %d)</error>';
            $output->writeln(sprintf($errorMsg, $error->getCode()));
            exit(1);
        } catch (UnexpectedBodyResponseException $_) {
            $errorMsg = '<error>Cannot retrieve language list, Mal formated PoEditor response</error>';
            $output->writeln($errorMsg);
            exit(1);
        }

        $countLanguages = count($languages);
        if ($countLanguages === 0) {
            $errorMsg = '<comment>0 languages found in project %d</comment>';
            $output->writeln(sprintf($errorMsg, $projectId));
            exit(0);
        } else {
            $output->writeln(
                sprintf('%d languages has been correctly retrieve.', $countLanguages),
                OutputInterface::VERBOSITY_VERBOSE
            );
        }
        foreach ($languages as $language) {
            $code = mb_strtolower($language->formatCode());
            $outputFile = $input->getOption(self::_OPTION_OUTPUT_DIR_);
            $outputFile .= sprintf('/%s/LC_MESSAGES/default.mo', $code);

            try {
                $language->exportToMo($outputFile);
            } catch (IOException $_) {
                $errorMsg = '<error>Cannot export mo file to %s</error>';
                $output->writeln(sprintf($errorMsg, $outputFile));
                exit(1);
            } catch (ApiErrorException $error) {
                $errorMsg = '<error>Cannot export mo file to %s, unexpected PoEditor response (status code %d)</error>';
                $output->writeln(sprintf($errorMsg, $outputFile, $error->getCode()));
                exit(1);
            } catch (UnexpectedBodyResponseException $_) {
                $errorMsg = '<error>Cannot export mo file to %s, Mal formated PoEditor response</error>';
                $output->writeln(sprintf($errorMsg, $outputFile));
                exit(1);
            }
            $output->writeln(
                sprintf(
                    '<info>`%s` file has been successfully imported at `%s`</info>',
                    $code,
                    $outputFile
                )
            );
        }
    }
}
