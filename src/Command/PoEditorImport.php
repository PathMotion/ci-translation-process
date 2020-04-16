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
use PathMotion\CI\Utils\TranslationFile;
use Symfony\Component\Console\Input\InputArgument;

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
     * Long command line option for file output
     * @var string
     */
    const _OPTION_OUTPUT_MASK_ = 'mask';

    /**
     * Short command line option for file output
     * @var string
     */
    const _SHORT_OPTION_OUTPUT_MASK_ = 'm';

    /**
     * Long command line option for file output
     * @var string
     */
    const _OPTION_IETF_TAG_ = 'code';

    /**
     * Short command line option for file output
     * @var string
     */
    const _SHORT_OPTION_IETF_TAG_ = 'c';

    /**
     * Default value of command line option self::_OPTION_FILE_NAME_CODE_
     * @var string
     */
    const _DEFAULT_FILE_NAME_CODE_ = 'POSIX';

    /**
     * Default value of command line option self::_OPTION_OUTPUT_MASK_
     * @var string
     */
    const _DEFAULT_OUTPUT_MASK_ = '/%s/LC_MESSAGES/default';

    /**
     * Default value of command line option self::_OPTION_FILE_TYPE_
     * @var string
     */
    const _DEFAULT_FILE_TYPE_ = 'mo';

    /**
     * Long command line option for the name of
     * the environment variable, that contains the API key.
     * @var string
     */
    const _OPTION_CONTEXT_ = 'context';

    /**
     * Short command line option for imported file type
     * @var string
     */
    const _SHORT_OPTION_CONTEXT_ = 'c';

    const _ALLOWED_FILE_NAME_CODE_ = [
        'posix' => Language::FORMAT_POSIX,
        'iso_639_1' => Language::FORMAT_ISO_639_1
    ];

    const _ALLOWED_OPTION_FILE_TYPE_ = [
        'po' => 'po',
        'pot' => 'pot',
        'mo' => 'mo',
        'xls' => 'xls',
        'xlsx' => 'xlsx',
        'csv' => 'csv',
        'ini' => 'ini',
        'resw' => 'resw', //Windows
        'resx' => 'resx', // Windows
        'android_strings' => 'xml', // Android
        'apple_strings' => 'strings', // iOS
        'xliff' => 'xliff', // iOS
        'properties' => 'properties', // Java
        'key_value_json' => 'json',
        'json' => 'json',
        'yml' => 'yml',
        'xmb' => 'xmb',
        'xtb' => 'xtb'
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
        $this->setDescription('Import translation files from PoEditor.com account.');

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
            'Name of the environment variable, that contains the PoEditor API key.',
            self::_DEFAULT_VALUE_API_KEY_ENV_
        );

        $this->addOption(
            self::_OPTION_PROJECT_,
            self::_SHORT_OPTION_PROJECT_,
            InputOption::VALUE_REQUIRED,
            'Po editor project identifiers'
        );

        $this->addOption(
            self::_OPTION_FILE_TYPE_,
            self::_SHORT_OPTION_FILE_TYPE_,
            InputOption::VALUE_REQUIRED,
            'Imported file type',
            self::_DEFAULT_FILE_TYPE_
        );

        $this->addOption(
            self::_OPTION_CONTEXT_,
            self::_SHORT_OPTION_CONTEXT_,
            InputOption::VALUE_NONE,
            'split context into several file'
        );

        // IETF format
        $this->addOption(
            self::_OPTION_OUTPUT_MASK_,
            self::_SHORT_OPTION_OUTPUT_MASK_,
            InputOption::VALUE_REQUIRED,
            'Output file mask',
            self::_DEFAULT_OUTPUT_MASK_
        );

        $this->addOption(
            self::_OPTION_IETF_TAG_,
            self::_SHORT_OPTION_IETF_TAG_,
            InputOption::VALUE_REQUIRED,
            'Language code format (IETF tag lang)',
            self::_DEFAULT_FILE_NAME_CODE_
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

        // File type check
        $fileType = mb_strtolower($input->getOption(self::_OPTION_FILE_TYPE_));
        $input->setOption(self::_OPTION_FILE_TYPE_, $fileType);

        if (!isset(self::_ALLOWED_OPTION_FILE_TYPE_[$fileType])) {
            $errorMsg = 'The "--%s" option got "%s" only "%s" are allowed.';
            throw new RuntimeException(sprintf(
                $errorMsg,
                self::_OPTION_FILE_TYPE_,
                $fileType,
                implode('", "', array_keys(self::_ALLOWED_OPTION_FILE_TYPE_))
            ));
        }

        // Check language code format
        $languageCodeFormat = mb_strtolower($input->getOption(self::_OPTION_IETF_TAG_));

        if (!isset(self::_ALLOWED_FILE_NAME_CODE_[$languageCodeFormat])) {
            $errorMsg = 'The "--%s" option got "%s" only "%s" are allowed.';
            throw new RuntimeException(sprintf(
                $errorMsg,
                self::_OPTION_IETF_TAG_,
                $languageCodeFormat,
                implode('", "', array_keys(self::_ALLOWED_FILE_NAME_CODE_))
            ));
        }
        $input->setOption(self::_OPTION_IETF_TAG_, self::_ALLOWED_FILE_NAME_CODE_[$languageCodeFormat]);

        // Api Key initialization
        $apiKeyEnv = $input->getOption(self::_OPTION_API_KEY_ENV_);
        $apiKey = getenv($apiKeyEnv);

        if (!is_string($apiKey) || empty($apiKey)) {
            return $this->fatalError(sprintf('Environment variable `%s` not found', $apiKeyEnv));
        }
        $this->initPoEditorClient($apiKey);
    }

    /**
     * Add and offend secret information
     * @return array
     */
    protected function debugInput(): array
    {
        $inputValues = parent::debugInput();

        $apiKeyEnv = $this->getInput()->getOption(self::_OPTION_API_KEY_ENV_);
        $apiKey = getenv($apiKeyEnv);

        if (!is_string($apiKey) || empty($apiKey)) {
            return $inputValues;
        }
        $apiKey = trim($apiKey);
        if (empty($apiKey)) {
            $inputValues['env'][$apiKeyEnv] = '-- EMPTY STRING --';
            return $inputValues;
        }
        $keyLen = strlen($apiKey);
        $visibleLen = 4;
        if ($keyLen === 1) {
            $inputValues['env'][$apiKeyEnv] = '*';
            return $inputValues;
        } else if ($keyLen <= $visibleLen) {
            $visibleLen = 1;
        }
        $apiKey = str_repeat('*', $keyLen - $visibleLen) . substr($apiKey, -1 * $visibleLen);
        $inputValues['env'][$apiKeyEnv] = $apiKey;
        return $inputValues;
        return [];
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
            $this->importFile($language, $input->getOption(self::_OPTION_CONTEXT_));
        }
    }

    /**
     * Import file into the file system
     * @param Language $language
     * @param bool $extractContrext
     * @return TranslationFile
     */
    private function importFile(Language $language, bool $extractContext): TranslationFile
    {
        $languageCodeFormat = $this->getInput()->getOption(self::_OPTION_IETF_TAG_);
        $code = mb_strtolower($language->formatCode($languageCodeFormat));
        $fileType = $this->getInput()->getOption(self::_OPTION_FILE_TYPE_);
        $outputFile = $this->getInput()->getOption(self::_OPTION_DESTINATION_);
        $outputMask = $this->getInput()->getOption(self::_OPTION_OUTPUT_MASK_);
        $outputFile .= sprintf($outputMask . '.%s', $code, self::_ALLOWED_OPTION_FILE_TYPE_[$fileType]);
        $contextFiles = [];

        try {
            $translationFile = $language->exportTo($fileType, $outputFile);

            if ($extractContext) {
                $contextFiles = $translationFile->extractContext();
            }
        } catch (IOException $_) {
            return $this->fatalError(sprintf('Cannot export mo file to %s', $outputFile));
        } catch (ApiErrorException $error) {
            return $this->fatalError(sprintf(
                'Cannot export mo file to %s, unexpected PoEditor response (status code %d)',
                $outputFile,
                $error->getCode()
            ));
        } catch (UnexpectedBodyResponseException $_) {
            return $this->fatalError(sprintf(
                'Cannot export mo file to %s, unexpected PoEditor response',
                $outputFile
            ));
        }
        $this->writeln(sprintf('`%s` file has been successfully imported at `%s`', $code, $outputFile), 'info');
        if ($extractContext === false) {
            return $translationFile;
        }
        if (count($contextFiles) === 0) {
            $this->writeln('There is no other context than `default`', 'info');
        }
        foreach ($contextFiles as $filePath) {
            $this->writeln(sprintf('Context file has been successfully imported at `%s`', $filePath), 'info');
        }
        return $translationFile;
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
            $errorMsg = 'Cannot retrieve project %d, unexpected PoEditor API response';
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
            return $this->fatalError(sprintf(
                'Cannot retrieve language list, unexpected PoEditor response (status code %d)',
                $error->getCode()
            ));
        } catch (UnexpectedBodyResponseException $_) {
            return $this->fatalError('Cannot retrieve language list, unexpected PoEditor API response');
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
