<?php
namespace PathMotion\CI\Utils\FileSystem;

use LogicException;

class DirectoriesCollection extends FsItemsCollection
{
    /**
     * Add a new Directory item
     * @param Directory $item
     * @return self
     */
    public function add(FsItem $item): self
    {
        if ($item instanceof Directory === false) {
            $msg = '%s Argument `item` must be an instance of `%s`, instance of `%s` given';
            $formattedMsg = sprintf($msg, __METHOD__, Directory::class, get_class($item));
            throw new LogicException($formattedMsg);
        }
        return parent::add($item);
    }
}
