<?php
namespace PathMotion\CI\Command;

use Gettext\Loader\PoLoader;
use Gettext\Translations;
use PathMotion\CI\Utils\FileSystem\Directory;
use PathMotion\CI\Utils\FileSystem\Query\WhereClause;
use PathMotion\CI\Utils\PoFile;
use PathMotion\CI\Utils\PoFiles;
use PathMotion\CI\Utils\Scanner\BladeScanner;
use PathMotion\CI\Utils\Scanner\PhpScanner;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePoFromCode extends AbstractCommand
{
    /**
     * Default command name
     * @var string
     */
    protected static $defaultName = 'update-po-from-code';

    /**
     * Supported source languages
     * @var array
     */
    const LANGUAGES = [
        'php' => [
            'scanner' => PhpScanner::class,
            'extensions' => '.php'
        ],
        'php-with-blade' => [
            'scanner' => BladeScanner::class,
            'extensions' => '.php'
        ]
    ];

    /**
     * Long command line option to specify source code language
     * @var string
     */
    const OPTION_LANGUAGES = 'language';

    /**
     * Short command line option to specify source code language
     * @var string
     */
    const SHORT_OPTION_LANGUAGES = 'l';

    /**
     * Long command line option to specify source code path directory
     * @var string
     */
    const OPTION_SOURCE = 'source';

    /**
     * Short command line option to specify source code path directory
     * @var string
     */
    const SHORT_OPTION_SOURCE = 's';

    /**
     * Long command line option to specify output directory
     * @var string
     */
    const OPTION_OUTPUT = 'output';

    /**
     * Short command line option to specify output directory
     * @var string
     */
    const SHORT_OPTION_OUTPUT = 'o';

    /**
     * Long command line option for deletion strategy
     * @var string
     */
    const OPTION_REMOVE_UNUSED = 'remove-unused';

    /**
     * Long command line option for deletion strategy
     * @var string
     */
    const OPTION_DISABLE_UNUSED = 'disable-unused';

    /**
     * Long command line option for deletion strategy
     * @var string
     */
    const OPTION_ADD_COMMENT_UNUSED = 'add-comment-unused';

    /**
     * Source directory
     * @var Directory
     */
    private $sourceDirectory;

    /**
     * Output directory
     * @var Directory
     */
    private $outputDirectory;

    /**
     * Deletion strategy
     * @var int
     */
    private $deletionStrategy = PoFiles::DELETION_STRATEGY_NOTHING;

    /**
     * Configure command description and options
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Update PO files from source code.');

        $this->addOption(
            self::OPTION_OUTPUT,
            self::SHORT_OPTION_OUTPUT,
            InputOption::VALUE_REQUIRED,
            'Directory where output po files'
        );

        $this->addOption(
            self::OPTION_SOURCE,
            self::SHORT_OPTION_SOURCE,
            InputOption::VALUE_REQUIRED,
            'Source code path directory'
        );

        $this->addOption(
            self::OPTION_LANGUAGES,
            self::SHORT_OPTION_LANGUAGES,
            InputOption::VALUE_IS_ARRAY + InputOption::VALUE_REQUIRED,
            'Source code language (' . implode(', ', array_keys(self::LANGUAGES)) . ')'
        );

        $this->addOption(
            self::OPTION_REMOVE_UNUSED,
            null,
            InputOption::VALUE_NONE,
            'Remove a translation if it not present in source code'
        );

        $this->addOption(
            self::OPTION_DISABLE_UNUSED,
            null,
            InputOption::VALUE_NONE,
            'Disable a translation if it not present in source code'
        );

        $this->addOption(
            self::OPTION_ADD_COMMENT_UNUSED,
            null,
            InputOption::VALUE_NONE,
            'Add a comment for a translation if it not present in source code'
        );
    }

    /**
     * Option validation
     * @throws RuntimeException
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->checkInputLanguageOptions($input);

        $sourceCodeDir = $input->getOption(self::OPTION_SOURCE);
        if (!is_dir($sourceCodeDir)) {
            throw new RuntimeException('Source code directory option should be an existing path');
        } else {
            $this->sourceDirectory = new Directory($sourceCodeDir);
        }

        $outputCodeDir = $input->getOption(self::OPTION_OUTPUT);
        if (!is_dir($outputCodeDir)) {
            throw new RuntimeException('Output directory option should be an existing path');
        } else {
            $this->outputDirectory = new Directory($outputCodeDir);
        }

        if ($input->getOption(self::OPTION_REMOVE_UNUSED)) {
            $this->deletionStrategy += PoFiles::DELETION_STRATEGY_DELETE;
        }
        if ($input->getOption(self::OPTION_DISABLE_UNUSED)) {
            $this->deletionStrategy += PoFiles::DELETION_STRATEGY_DISABLE;
        }
        if ($input->getOption(self::OPTION_ADD_COMMENT_UNUSED)) {
            $this->deletionStrategy += PoFiles::DELETION_STRATEGY_ADD_COMMENT;
        }
    }

    /**
     * Check input languages options validity
     * @param InputInterface $input
     * @return void
     */
    private function checkInputLanguageOptions(InputInterface $input): void
    {
        $languages = $input->getOption(self::OPTION_LANGUAGES);

        if (empty($languages)) {
            $errorMsg = 'The "--%s" option is required and only "%s" are allowed.';
            throw new RuntimeException(sprintf(
                $errorMsg,
                self::OPTION_LANGUAGES,
                implode('", "', array_keys(self::LANGUAGES))
            ));
        }
        foreach ($languages as $language) {
            $language = mb_strtolower($language);
            if (empty(self::LANGUAGES[$language])) {
                $errorMsg = 'The "--%s" option got "%s" only "%s" are allowed.';
                throw new RuntimeException(sprintf(
                    $errorMsg,
                    self::OPTION_LANGUAGES,
                    $language,
                    implode('", "', array_keys(self::LANGUAGES))
                ));
            }
        }
    }

    /**
     * Get existing PO translations
     * @return PoFiles
     */
    private function getExistingPoTranslations(): PoFiles
    {
        $loader = new PoLoader();
        $files = new PoFiles();
        $poFiles = $this->outputDirectory->findRecursively(WhereClause::isNotDir(), WhereClause::extension('po'));

        foreach ($poFiles as $key => $file) {
            $poFile = new PoFile($loader->loadFile($key), $key);
            $domain = $file->basename('.po');
            $categoryDir = $file->parent();
            $languageDir = $categoryDir ? $categoryDir->parent() : null;
            $language = $languageDir ? $languageDir->baseName() : null;

            $poFile->setDomain($domain);
            if ($language) {
                $poFile->setLanguage($language);
            }
            $files->add($poFile);
            $this->writeln(sprintf('Po file found `%s`/`%s` (%s)', $language, $domain, $key));
        }
        return $files;
    }

    /**
     * Get translations from source code
     * @param array $codeLanguages
     * @param array $domains
     * @return array
     */
    private function getTranslationsFromSourceCode(array $codeLanguages, array $domains = ['default']): array
    {
        $domainsTranslations = [];
        $domainsTranslations['default'] = Translations::create('default');
        foreach ($domains as $domain) {
            $domainsTranslations[$domain] = Translations::create($domain);
        }
        foreach ($codeLanguages as $languageName) {
            $language = self::LANGUAGES[$languageName];
            $scanner = new $language['scanner']($domainsTranslations['default']);
            foreach ($domainsTranslations as $translations) {
                $scanner->addTranslations($translations);
            }
            $scanner->setDefaultDomain('default');
            $scanner->ignoreInvalidFunctions();

            $sourceFiles = $this->sourceDirectory->findRecursively(
                WhereClause::isNotDir(),
                WhereClause::extension($language['extensions'])
            );

            $this->writeln(sprintf('Language source code `%s`: %d file(s) will be scanned', $languageName, count($sourceFiles)));
            $previousCount = $scanner->getTranslationCount();
            foreach ($sourceFiles as $file) {
                $scanner->scanFile($file->getPath());
                $newCount = $scanner->getTranslationCount();
                $diff = $newCount - $previousCount;

                if ($diff === 0) {
                    continue;
                }
                $this->writeln(sprintf('Language source code `%s`: %d new translation(s) found in %s', $languageName, $diff, $file->getPath()));
                $previousCount = $newCount;
            }
            $this->writeln(sprintf('Language source code `%s`: %d translation(s) found', $languageName, $scanner->getTranslationCount()));
        }
        return $domainsTranslations;
    }

    public function runCommandLogic(InputInterface $input)
    {
        $languages = $input->getOption(self::OPTION_LANGUAGES);
        $existingTranslations = $this->getExistingPoTranslations();
        $domains = $existingTranslations->getDomains();
        $translationsFromSource = $this->getTranslationsFromSourceCode($languages, $domains);

        foreach ($translationsFromSource as $domain => $translations) {
            $stats = $existingTranslations->updateWithSourceTranslation($translations, $this->deletionStrategy);
            foreach ($stats as $file => $stat) {
                $this->writeln(sprintf('File %s', $file));
                $this->writeln(sprintf('  - %d translation(s) added', $stat['added']));
                $this->writeln(sprintf('  - %d translation(s) updated', $stat['updated']));
                $this->writeln(sprintf('  - %d translation(s) deleted', $stat['deleted']));
            }
        }
        $existingTranslations->save();
    }
}
