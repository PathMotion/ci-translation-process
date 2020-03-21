<?php
namespace PathMotion\CI\Utils;

use Gettext\Generator\PoGenerator;
use Gettext\Merge;
use Gettext\Translations;

class PoFile
{
    /**
     * Po File path
     * @var string
     */
    protected $path;

    private const DEFAULT_DOMAIN = 'default';

    /**
     * Translations
     * @var Translations
     */
    protected $translations;

    public function __construct(Translations $translations, string $path)
    {
        $this->translations = $translations;
        $this->path = $path;
    }

    public function setLanguage(string $language): self
    {
        $this->translations->setLanguage($language);
        return $this;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function setDomain(string $domain): self
    {
        $this->translations->setDomain($domain);
        return $this;
    }

    public function getDomain(): string
    {
        $domain = $this->translations->getDomain();
        if ($domain) {
            return $domain;
        }
        return self::DEFAULT_DOMAIN;
    }

    public function updateWithSourceTranslation(Translations $translations)
    {
        $this->translations = $this->translations->mergeWith($translations, Merge::TRANSLATIONS_OURS + Merge::HEADERS_OURS + Merge::REFERENCES_THEIRS);
        return $this;
    }

    public function save(): self
    {
        $generator = new PoGenerator();
        $generator->generateFile($this->translations, $this->path);
        return $this;
    }
}
