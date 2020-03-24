<?php
namespace PathMotion\CI\Utils;

use Gettext\Generator\PoGenerator;
use Gettext\Translation;
use Gettext\Translations;

/**
 * PoFile is a link between a list of translations and a file path
 */
class PoFile
{
    /**
     * Po File path
     * @var string
     */
    protected $path;

    /**
     * Default PO file domain
     * @var string
     */
    private const DEFAULT_DOMAIN = 'default';

    /**
     * Translations
     * @var Translations
     */
    protected $translations;
    
    const DELETION_FLAG_COMMENT = 'this translation occurrence was not found in the code';

    public function __construct(Translations $translations, string $path)
    {
        $this->translations = $translations;
        $this->path = $path;
    }

    /**
     * Get file path
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Set language to the translations
     * @param string $language
     * @return self
     */
    public function setLanguage(string $language): self
    {
        $this->translations->setLanguage($language);
        return $this;
    }

    /**
     * Set domain to the translations
     * @param string $domain
     * @return self
     */
    public function setDomain(string $domain): self
    {
        $this->translations->setDomain($domain);
        return $this;
    }

    /**
     * Get translations domain
     * @return string
     */
    public function getDomain(): string
    {
        $domain = $this->translations->getDomain();
        if ($domain) {
            return $domain;
        }
        return self::DEFAULT_DOMAIN;
    }

    /**
     * Update translation with incoming translation
     * Return an array of stats which contain
     *  - deleted (int)
     *  - updated (int)
     *  - added (int)
     * @param Translations $translations
     * @param int $deletionStrategy
     * @return array
     */
    public function updateWithSourceTranslation(Translations $translations, int $deletionStrategy = 0): array
    {
        $stats = ['deleted' => 0, 'updated' => 0, 'added' => 0];
        $atDeletionAddComment = $deletionStrategy & PoFiles::DELETION_STRATEGY_ADD_COMMENT;
        $atDeletionDisable = $deletionStrategy & PoFiles::DELETION_STRATEGY_DISABLE;
        foreach ($this->translations as $key => $localTranslation) {
            $context = $localTranslation->getContext();
            $original = $localTranslation->getOriginal();

            $incomingTranslation = $translations->find($context, $original);
            $existInIncoming = $incomingTranslation !== null;

            if (!$existInIncoming) {
                $stats['deleted'] += 1;
                if ($deletionStrategy & PoFiles::DELETION_STRATEGY_DELETE) {
                    $this->translations->remove($localTranslation);
                    continue;
                }
                $this->translations->add($this->softDeleteTranslation($localTranslation, $atDeletionAddComment, $atDeletionDisable));
                continue;
            } else {
                $stats['updated'] += 1;
                $this->translations->add($this->updateTranslation($localTranslation, $incomingTranslation));
            }
        }
        foreach ($translations as $key => $incomingTranslation) {
            $context = $incomingTranslation->getContext();
            $original = $incomingTranslation->getOriginal();

            $exist = $this->translations->find($context, $original) !== null;
            if (!$exist) {
                $stats['added'] += 1;
                $this->translations->add($incomingTranslation);
            }
        }
        return $stats;
    }

    /**
     * Soft delete translation
     * Will remove translation references
     * if [$addComment] is set as `true` so it will add an custom comment
     * if [$forceDisable] is set as `true` so it will disable the translation
     * @param Translation $local
     * @param boolean $addComment
     * @param boolean $forceDisable
     * @return Translation
     */
    private function softDeleteTranslation(Translation $local, bool $addComment = true, bool $forceDisable = false): Translation
    {
        $newTranslation = Translation::create($local->getContext(), $local->getOriginal());
        $newTranslation->translate($local->getTranslation());

        if ($forceDisable) {
            $newTranslation->disable(true);
        } else {
            $newTranslation->disable($local->isDisabled());
        }

        // Plural keep local
        $plural = $local->getPlural();
        if ($plural) {
            $newTranslation->setPlural($plural);
            $newTranslation->translatePlural(...$local->getPluralTranslations());
        }

        // Flags keep local
        foreach ($local->getFlags() as $comment) {
            $newTranslation->getFlags()->add($comment);
        }

        // Comments keep local
        foreach ($local->getComments() as $comment) {
            $newTranslation->getComments()->add($comment);
        }

        // Extracted comments replace with incoming
        foreach ($local->getExtractedComments() as $comment) {
            $newTranslation->getExtractedComments()->add($comment);
        }

        $newTranslation->getComments()->delete(self::DELETION_FLAG_COMMENT);
        if ($addComment) {
            $newTranslation->getComments()->add(self::DELETION_FLAG_COMMENT);
        }
        return $newTranslation;
    }

    /**
     * Update a translation with an other translation
     * (only replace references and Extracted comments)
     * @param Translation $local
     * @param Translation $changed
     * @return Translation
     */
    private function updateTranslation(Translation $local, Translation $changed): Translation
    {
        $newTranslation = Translation::create($local->getContext(), $local->getOriginal());

        // translate keep local
        $newTranslation->translate($local->getTranslation());

        // disable keep local
        $newTranslation->disable($local->isDisabled());

        // Plural keep local
        $plural = $local->getPlural();
        if ($plural) {
            $newTranslation->setPlural($plural);
            $newTranslation->translatePlural(...$local->getPluralTranslations());
        }

        // Flags keep local
        foreach ($local->getFlags() as $comment) {
            $newTranslation->getFlags()->add($comment);
        }

        // Comments keep local
        foreach ($local->getComments() as $comment) {
            $newTranslation->getComments()->add($comment);
        }

        // Extracted comments replace with incoming
        foreach ($changed->getExtractedComments() as $comment) {
            $newTranslation->getExtractedComments()->add($comment);
        }

        // References replace with incoming
        foreach ($changed->getReferences() as $key => $lines) {
            foreach ($lines as $line) {
                $newTranslation->getReferences()->add($key, $line);
            }
        }
        return $newTranslation;
    }

    /**
     * Save file
     * @return self
     */
    public function save(): self
    {
        $generator = new PoGenerator();
        $generator->generateFile($this->translations, $this->path);

        return $this;
    }
}
