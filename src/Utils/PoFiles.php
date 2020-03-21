<?php
namespace PathMotion\CI\Utils;

use Gettext\Translations;

class PoFiles
{
    /**
     * Translations
     * @var array<PoFile>
     */
    protected $files = [];

    protected $domains = [];

    public function add(PoFile $file): self
    {
        $this->files[] = $file;

        $domain = $file->getDomain();
        $this->domains[$domain] = $domain;
        return $this;
    }

    public function getDomains(): array
    {
        return array_keys($this->domains);
    }

    public function filterByDomain(string $domain): PoFiles
    {
        $files = new PoFiles();

        foreach ($this->files as $file) {
            if ($file->getDomain() !== $domain) {
                continue;
            }
            $files->add($file);
        }
        return $files;
    }

    public function updateWithSourceTranslation(Translations $translations): self
    {
        $files = $this->filterByDomain($translations->getDomain());

        foreach ($files->files as $file) {
            $file->updateWithSourceTranslation($translations);
        }
        return $this;
    }

    public function save(): self
    {
        foreach ($this->files as $file) {
            $file->save();
        }
        return $this;
    }
}
