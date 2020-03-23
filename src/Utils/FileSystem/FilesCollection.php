<?php
namespace PathMotion\CI\Utils\FileSystem;

use LogicException;

/**
 * Files collection will centralize and ensure list of Files
 */
class FilesCollection extends FsItemsCollection
{
    /**
     * Add a new Directory item
     * @param File $item
     * @return self
     */
    public function add(FsItem $item): self
    {
        if ($item instanceof File === false) {
            $msg = '%s Argument `item` must be an instance of `%s`, instance of `%s` given';
            $formattedMsg = sprintf($msg, __METHOD__, File::class, get_class($item));
            throw new LogicException($formattedMsg);
        }
        return parent::add($item);
    }
}
