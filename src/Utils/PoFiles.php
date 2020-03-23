<?php
namespace PathMotion\CI\Utils;

use Gettext\Translations;

class PoFiles
{
    /**
     * Do nothing when a translation is unused
     * @var int
     */
    const DELETION_STRATEGY_NOTHING = 1 << 0;

    /**
     * Add comment when a translation is unused
     * @var int
     */
    const DELETION_STRATEGY_ADD_COMMENT = 1 << 1;

    /**
     * Disable when a translation is unused
     * @var int
     */
    const DELETION_STRATEGY_DISABLE = 1 << 2;

    /**
     * Remove when a translation is unused
     * @var int
     */
    const DELETION_STRATEGY_DELETE = 1 << 3;

    /**
     * Translations
     * @var array<PoFile>
     */
    protected $files = [];

    /**
     * Files domains
     * @var array
     */
    protected $domains = [];

    /**
     * Add a new file to the po files list
     * @param PoFile $file
     * @return self
     */
    public function add(PoFile $file): self
    {
        $this->files[] = $file;

        $domain = $file->getDomain();
        $this->domains[$domain] = $domain;
        return $this;
    }

    /**
     * Get all domains
     * @return array <string>
     */
    public function getDomains(): array
    {
        return array_keys($this->domains);
    }

    /**
     * Filter files by domain
     * @param string $domain
     * @return PoFiles
     */
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

    /**
     * Update translations files with an other translations collection
     *
     * @param Translations $translations
     * @param integer $deletionStrategy
     * @return array
     */
    public function updateWithSourceTranslation(Translations $translations, int $deletionStrategy = 0): array
    {
        $stats = [];
        $files = $this->filterByDomain($translations->getDomain());

        foreach ($files->files as $file) {
            $stats[$file->getPath()] = $file->updateWithSourceTranslation($translations, $deletionStrategy);
        }
        return $stats;
    }

    /**
     * Save po files
     * @return self
     */
    public function save(): self
    {
        foreach ($this->files as $file) {
            $file->save();
        }
        return $this;
    }
}
