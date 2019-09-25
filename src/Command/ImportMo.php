<?php
namespace PathMotion\CI\Command;

use LogicException;
use PathMotion\CI\PoEditor\Client as PoEditorClient;
use PathMotion\CI\PoEditor\Exception\ApiErrorException;
use PathMotion\CI\PoEditor\Exception\IOException;
use PathMotion\CI\PoEditor\Exception\UnexpectedBodyResponseException;
use PathMotion\CI\PoEditor\Project;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;

class ImportMo extends AbstractCommand
{
    protected static $defaultName = 'import-mo';

    const _OPTION_OUTPUT_DIR_ = 'output-dir';

    const _OPTION_API_KEY_ENV_ = 'api-key-env-name';

    const _OPTION_PROJECT_ID_ = 'project-id';

    const _DEFAULT_API_KEY_ENV_ = 'PO_EDITOR_API_KEY';

    /**
     * Po Editor Client
     * @var PoEditorClient|null
     */
    private $poEditorClient = null;

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

    /**
     * Command logic
     *
     * @param InputInterface $input
     * @return void
     */
    public function runCommandLogic(InputInterface $input)
    {
        $apiKeyEnv = $input->getOption(self::_OPTION_API_KEY_ENV_);
        $apiKey = getenv($apiKeyEnv);

        if (!is_string($apiKey)) {
            return $this->fatalError(sprintf('Environment variable `%s` not found', $apiKeyEnv));
        }
        $this->initPoEditorClient($apiKey);

        // Project
        $projectId= (int)$input->getOption(self::_OPTION_PROJECT_ID_);
        $project = $this->retrieveProject($projectId);
        $this->verboseLn(sprintf('Project `%s`(%d) has been correctly retrieve', $project->getName(), $projectId));

        // Languages
        $languages = $this->retrieveLanguages($project);

        // Importation
        foreach ($languages as $language) {
            $code = mb_strtolower($language->formatCode());
            $outputFile = $input->getOption(self::_OPTION_OUTPUT_DIR_);
            $outputFile .= sprintf('/%s/LC_MESSAGES/default.mo', $code);

            try {
                $language->exportToMo($outputFile);
            } catch (IOException $_) {
                return $this->fatalError(sprintf('Cannot export mo file to %s', $outputFile));
            } catch (ApiErrorException $error) {
                return $this->fatalError(sprintf('Cannot export mo file to %s, unexpected PoEditor response (status code %d)', $outputFile, $error->getCode()));
            } catch (UnexpectedBodyResponseException $_) {
                return $this->fatalError(sprintf('Cannot export mo file to %s, Mal formated PoEditor response', $outputFile));
            }
            $this->writeln(sprintf('`%s` file has been successfully imported at `%s`', $code, $outputFile), 'info');
        }
    }

    /**
     * Initialize Po Editor client
     * @param string $apiKey
     * @return self
     */
    private function initPoEditorClient(string $apiKey): self
    {
        $this->poEditorClient = new PoEditorClient($apiKey);
        return $this;
    }

    /**
     * Get po Editor client instance
     * @throws LogicException
     * @return PoEditorClient
     */
    private function getPoEditorClient(): PoEditorClient
    {
        if (!isset($this->poEditorClient)) {
            throw new LogicException('Po Editor client must be initialize');
        }
        return $this->poEditorClient;
    }

    /**
     * Retrieve po editor project information
     * @param integer $projectId
     * @return Project
     */
    private function retrieveProject(int $projectId): Project
    {
        try {
            $project = $this->getPoEditorClient()->getProject($projectId);
        } catch (ApiErrorException $error) {
            $errorMsg = 'Cannot retrieve project %d, unexpected PoEditor response (status code %d)';
            return $this->fatalError(sprintf($errorMsg, $projectId, $error->getCode()));
        } catch (UnexpectedBodyResponseException $_) {
            $errorMsg = 'Cannot retrieve project %d, Mal formated PoEditor API response';
            return $this->fatalError(sprintf($errorMsg, $projectId));
        }
        return $project;
    }

    /**
     * Retrieve po editor project languages
     * @param Project $project
     * @return array <Language>
     */
    private function retrieveLanguages(Project $project)
    {
        $languages = [];
        try {
            $languages = $project->languagesList();
        } catch (ApiErrorException $error) {
            return $this->fatalError(sprintf('Cannot retrieve language list, unexpected PoEditor response (status code %d)', $error->getCode()));
        } catch (UnexpectedBodyResponseException $_) {
            return $this->fatalError('Cannot retrieve language list, Mal formated PoEditor API response');
        }
        $countLanguages = count($languages);
        if ($countLanguages === 0) {
            $this->writeln(sprintf('0 languages found in project %d', $project->getId()), 'comment');
        } else {
            $this->verboseLn(sprintf('%d languages has been correctly retrieve.', $countLanguages));
        }
        return $languages;
    }
}
