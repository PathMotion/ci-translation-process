<?php
namespace PathMotion\CI\Utils;

use Exception;
use Gettext\Generator\Generator;
use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\Loader;
use Gettext\Loader\MoLoader;
use Gettext\Loader\PoLoader;
use Gettext\Translations;

class TranslationFile
{
    /**
     * Translation file path
     * @var string
     */
    private $filePath;

    /**
     * file loader
     * @var Loader|null
     */
    private $loader = null;

    /**
     * file generator
     * @var Generator|null
     */
    private $generator = null;

    /**
     * file translations
     * @var Translations|null
     */
    private $translations = null;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception('Translation file does not exist');
        }
        $this->filePath = $filePath;
        $ext = mb_strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'po') {
            $this->loader = new PoLoader();
            $this->generator = new PoGenerator();
        } elseif ($ext === 'mo') {
            $this->loader = new MoLoader();
            $this->generator = new MoGenerator();
        }
    }

    /**
     * Get file translations
     * @return Translations|null
     */
    public function getTranslations(): ?Translations
    {
        if ($this->loader === null) {
            return null;
        }
        if ($this->translations) {
            return $this->translations;
        }
        $this->translations = $this->loader->loadFile($this->filePath);
        return $this->translations;
    }

    /**
     * Get all translation indexed by contexts in this files
     * @return array <Translation>
     */
    public function getTranslationByContexts(): array
    {
        $contexts = [];

        foreach ($this->getTranslations() as $value) {
            $context = $value->getContext();
            if ($context === null) {
                continue;
            }
            if (!isset($contexts[$context])) {
                $contexts[$context] = [];
            }
            $contexts[$context][] = $value->withContext(null);
        }

        return $contexts;
    }

    /**
     * Extract translation context into different files
     * @return array <string>
     */
    public function extractContext()
    {
        if ($this->loader === null) {
            return [];
        }
        $originalTranslations = $this->getTranslations();
        $contextTranslations = $this->getTranslationByContexts();
        $generatedContextFiles = [];

        foreach ($contextTranslations as $context => $tr) {
            $contextTranslations = Translations::create($context, $originalTranslations->getLanguage());

            foreach ($tr as $translation) {
                $contextTranslations->add($translation);
            }
            $pathInfo = pathinfo($this->filePath);
            $newFilePath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $context . "." . $pathInfo['extension'];
            $this->generator->generateFile($contextTranslations, $newFilePath);
            $generatedContextFiles[] = $newFilePath;
        }
        return $generatedContextFiles;
    }
}
