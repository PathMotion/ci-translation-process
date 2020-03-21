<?php
namespace PathMotion\CI\Command;

use Exception;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Scanner\CodeScanner;
use Gettext\Translations;
use LogicException;
use PathMotion\CI\PoEditor\Client as PoEditorClient;
use PathMotion\CI\PoEditor\Exception\ApiErrorException;
use PathMotion\CI\PoEditor\Exception\IOException;
use PathMotion\CI\PoEditor\Exception\UnexpectedBodyResponseException;
use PathMotion\CI\PoEditor\Language;
use PathMotion\CI\PoEditor\Project;
use PathMotion\CI\Utils\PoFile;
use PathMotion\CI\Utils\PoFiles;
use PathMotion\CI\Utils\Scanner\BladeScanner;
use PathMotion\CI\Utils\Scanner\PhpScanner;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use PathMotion\CI\Utils\TranslationFile;
use Symfony\Component\Console\Input\InputArgument;

class ScanCode extends AbstractCommand
{
    protected static $defaultName = 'scan-code';

    const LANGUAGES = [
        'php' => [
            'scanner' => PhpScanner::class,
            'extensions' => ['.php']
        ],
        'php-with-blade' => [
            'scanner' => BladeScanner::class,
            'extensions' => ['.php']
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
     * Configuration command
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Scan code and create or update po files.');

        $this->addOption(
            'reset-file',
            'r',
            InputOption::VALUE_NONE,
            'Will reset all previous translations'
        );

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
        $this->checkInputLanguageOptions($input);
        $this->checkInputSourceCodePathDir($input);
        $this->checkInputOutputPathDir($input);
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
            throw new Exception('Languages option is required');
        }
        foreach ($languages as $language) {
            $language = mb_strtolower($language);
            if (empty(self::LANGUAGES[$language])) {
                throw new Exception('Languages option `' . $language . '` does not exist');
            }
        }
    }

    /**
     * Check input source options validity
     * @param InputInterface $input
     * @return void
     */
    private function checkInputSourceCodePathDir(InputInterface $input): void
    {
        $sourceCodeDir = $input->getOption(self::OPTION_SOURCE);

        if ($sourceCodeDir !== false && !is_dir($sourceCodeDir)) {
            throw new Exception('Source code directory option should be an existing path');
        }
    }

    /**
     * Check output options validity
     * @param InputInterface $input
     * @return void
     */
    private function checkInputOutputPathDir(InputInterface $input): void
    {
        $outputDir = $input->getOption(self::OPTION_OUTPUT);

        if ($outputDir !== false && !is_dir($outputDir)) {
            throw new Exception('Output directory option should be an existing path');
        }
    }

    /**
     * Command logic
     * @param InputInterface $input
     * @return void
     */
    public function runCommandLogic(InputInterface $input)
    {
        $source = $input->getOption(self::OPTION_SOURCE);
        $output = $input->getOption(self::OPTION_OUTPUT);
        $languages = $input->getOption(self::OPTION_LANGUAGES);
        $existingTranslations = $this->getExistingTranslations($output);
        $sourceCodeTranslations = $this->getTranslationsFromSourceCode(
            $source,
            $languages,
            $existingTranslations->getDomains()
        );

        foreach ($sourceCodeTranslations as $domain => $translations) {
            $existingTranslations->updateWithSourceTranslation($translations, $domain);
        }
        $existingTranslations->save();
        die;
        // DEBUG
        $poGenerator = new PoGenerator();
        $content = $poGenerator->generateString($sourceCodeTranslations);
        echo $content;

        // var_dump(array_keys($sourceTranslations->getTranslations()));
        die;


        // $source = realpath($input->getOption('source-dir'));
        // $scanner = new BladeScanner(
        //     Translations::create('default')
        // );
        // $scanner->setDefaultDomain('default');
        // $scanner->ignoreInvalidFunctions();

        // $this->scandir($scanner, $source, ['.blade.php']);
        // $translations = $scanner->getTranslations()['default']->getTranslations();
        // var_dump(array_keys($translations));
    }

    /**
     * Scan directory
     * @param CodeScanner $scanner
     * @param string $dirPath
     * @param array $extensions
     * @return void
     */
    private function scanSourceDir(CodeScanner $scanner, string $dirPath, array $extensions): void
    {
        $dir = array_diff(scandir($dirPath), ['.', '..']);

        foreach ($dir as $item) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->scanSourceDir($scanner, $path, $extensions);
                continue;
            }
            $find = false;
            foreach ($extensions as $extension) {
                if (preg_match('~' . preg_quote($extension, '~') . '$~', $item) > 0) {
                    $find = true;
                    break;
                }
            }
            if ($find === false) {
                continue;
            }
            $scanner->scanFile($path);
        }
    }

    /**
     * Get translations form source code
     * @param InputInterface $input
     * @return Translations
     */
    private function getTranslationsFromSourceCode(string $source, array $codeLanguages, array $domains = ['default']): array
    {
        $domainsTranslations = [];
        $domainsTranslations['default'] = Translations::create('default');
        foreach ($domains as $domain) {
            $domainsTranslations[$domain] = Translations::create($domain);
        }

        foreach ($codeLanguages as $language) {
            $language = self::LANGUAGES[$language];
            $scanner = new $language['scanner']($domainsTranslations['default']);
            foreach ($domainsTranslations as $translations) {
                $scanner->addTranslations($translations);
            }
            $scanner->setDefaultDomain('default');
            $scanner->ignoreInvalidFunctions();

            $this->scanSourceDir($scanner, $source, $language['extensions']);
        }
        return $domainsTranslations;
    }

    // private function scanOutputDir()

    private function getExistingTranslations(string $outputPath): PoFiles
    {
        $loader = new PoLoader();
        $items = array_diff(scandir($outputPath), ['.', '..']);
        $files = new PoFiles();

        foreach ($items as $language) {
            $languagePath = realpath($outputPath . DIRECTORY_SEPARATOR . $language);

            if (!is_dir($languagePath)) {
                continue;
            }
            $categories = array_diff(scandir($languagePath), ['.', '..']);
            foreach ($categories as $category) {
                $categoryPath = realpath($languagePath . DIRECTORY_SEPARATOR . $category);
                $poFiles = glob($categoryPath . DIRECTORY_SEPARATOR . '*.po');

                foreach ($poFiles as $poFilePath) {
                    $poFile = new PoFile($loader->loadFile($poFilePath), $poFilePath);
                    $poFile->setLanguage($language)
                        ->setCategory($category)
                        ->setDomain(basename($poFilePath, '.po'));

                        $files->add($poFile);
                }
            }
        }
        return $files;
    }
}
