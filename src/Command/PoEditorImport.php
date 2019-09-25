<?php
namespace PathMotion\CI\Command;

use LogicException;
use PathMotion\CI\PoEditor\Client as PoEditorClient;
use PathMotion\CI\PoEditor\Exception\ApiErrorException;
use PathMotion\CI\PoEditor\Exception\IOException;
use PathMotion\CI\PoEditor\Exception\UnexpectedBodyResponseException;
use PathMotion\CI\PoEditor\Language;
use PathMotion\CI\PoEditor\Project;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;

class PoEditorImport extends AbstractCommand
{
    protected static $defaultName = 'poeditor-import';

    /**
     * Long command line option for imported files destination
     * @var string
     */
    const _OPTION_DESTINATION_ = 'destination';

    /**
     * Short command line option for imported files destination
     * @var string
     */
    const _SHORT_OPTION_DESTINATION_ = 'd';

    /**
     * Long command line option for the name of
     * the environment variable, that contains the API key.
     * @var string
     */
    const _OPTION_API_KEY_ENV_ = 'api-key';

    /**
     * Default value of command line option self::_OPTION_API_KEY_ENV_
     * @var string
     */
    const _DEFAULT_VALUE_API_KEY_ENV_ = 'PO_EDITOR_API_KEY';

    /**
     * Long command line option for po editor project id
     * @var string
     */
    const _OPTION_PROJECT_ = 'project';

    /**
     * Short command line option for po editor project id
     * @var string
     */
    const _SHORT_OPTION_PROJECT_ = 'p';

    /**
     * Long command line option for imported file type
     * @var string
     */
    const _OPTION_FILE_TYPE_ = 'type';

    /**
     * Short command line option for imported file type
     * @var string
     */
    const _SHORT_OPTION_FILE_TYPE_ = 't';

    /**
     * Default value of command line option self::_OPTION_FILE_TYPE_
     * @var string
     */
    const _DEFAULT_FILE_TYPE_ = 'mo';

    const _ALLOWED_OPTION_FILE_TYPE_ = [
        'po', 'pot', 'mo', 'xls', 'xlsx', 'csv',
        'ini', 'resw', 'resx', 'android_strings',
        'apple_strings', 'xliff', 'properties',
        'key_value_json', 'json', 'yml', 'xmb',
        'xtb'
    ];

    /**
     * Po Editor Client
     * @var PoEditorClient|null
     */
    private $poEditorClient = null;

    /**
     * Configuration command
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Import MO from PoEditor.com account.');

        $this->addOption(
            self::_OPTION_DESTINATION_,
            self::_SHORT_OPTION_DESTINATION_,
            InputOption::VALUE_REQUIRED,
            'Destination of imported files'
        );

        $this->addOption(
            self::_OPTION_API_KEY_ENV_,
            null,
            InputOption::VALUE_REQUIRED,
            'Name of the environment variable, that contains the Poeditor API key.',
            self::_DEFAULT_VALUE_API_KEY_ENV_
        );

        $this->addOption(
            self::_OPTION_PROJECT_,
            self::_SHORT_OPTION_PROJECT_,
            InputOption::VALUE_REQUIRED,
            'Po editor project identifiant'
        );

        $this->addOption(
            self::_OPTION_FILE_TYPE_,
            self::_SHORT_OPTION_FILE_TYPE_,
            InputOption::VALUE_REQUIRED,
            'Imported file type',
            self::_DEFAULT_FILE_TYPE_
        );
    }

    /**
     * Initialize PoEditor Client + Option validation
     * @throws RuntimeException
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $fileType = mb_strtolower($input->getOption(self::_OPTION_FILE_TYPE_));
        $input->setOption(self::_OPTION_FILE_TYPE_, $fileType);
        if (!in_array($fileType, self::_ALLOWED_OPTION_FILE_TYPE_)) {
            $errorMsg = 'The "--%s" option got "%s" only "%s" are allowed.';
            throw new RuntimeException(sprintf(
                $errorMsg,
                self::_OPTION_FILE_TYPE_,
                $fileType, implode('", "', self::_ALLOWED_OPTION_FILE_TYPE_)
            ));
        }

        $apiKeyEnv = $input->getOption(self::_OPTION_API_KEY_ENV_);
        $apiKey = getenv($apiKeyEnv);

        if (!is_string($apiKey)) {
            return $this->fatalError(sprintf('Environment variable `%s` not found', $apiKeyEnv));
        }
        $this->initPoEditorClient($apiKey);
    }

    /**
     * Command logic
     *
     * @param InputInterface $input
     * @return void
     */
    public function runCommandLogic(InputInterface $input)
    {
        // Project
        $projectId= (int)$input->getOption(self::_OPTION_PROJECT_);
        $project = $this->retrieveProject($projectId);
        $this->verboseLn(sprintf('Project `%s`(%d) has been correctly retrieve', $project->getName(), $projectId));

        // Languages
        $languages = $this->retrieveLanguages($project);

        // Importation
        foreach ($languages as $language) {
            $this->importFile($language);
        }
    }

    private function importFile(Language $language)
    {
        $code = mb_strtolower($language->formatCode());
        $fileType = $this->getInput()->getOption(self::_OPTION_FILE_TYPE_);
        $outputFile = $this->getInput()->getOption(self::_OPTION_DESTINATION_);
        $outputFile .= sprintf('/%s/LC_MESSAGES/default.%s', $code, $fileType);

        try {
            $language->exportTo($fileType, $outputFile);
        } catch (IOException $_) {
            return $this->fatalError(sprintf('Cannot export mo file to %s', $outputFile));
        } catch (ApiErrorException $error) {
            return $this->fatalError(sprintf('Cannot export mo file to %s, unexpected PoEditor response (status code %d)', $outputFile, $error->getCode()));
        } catch (UnexpectedBodyResponseException $_) {
            return $this->fatalError(sprintf('Cannot export mo file to %s, Mal formated PoEditor response', $outputFile));
        }
        $this->writeln(sprintf('`%s` file has been successfully imported at `%s`', $code, $outputFile), 'info');
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