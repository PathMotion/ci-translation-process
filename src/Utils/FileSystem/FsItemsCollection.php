<?php
namespace PathMotion\CI\Utils\FileSystem;

use Countable;
use Iterator;

class FsItemsCollection implements Iterator, Countable
{
    /**
     * Collection of FsItem
     * @var array <FsItem>
     */
    private $items = [];

    /**
     * Collection of FsItem keys
     * @var array <string>
     */
    private $keys = [];

    /**
     * Iterator index
     * @var integer
     */
    private $iteratorIndex = 0;

    public function __construct(FsItem ...$items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * return count of items
     * @return integer
     */
    public function count(): int
    {
        return count($this->keys);
    }

    /**
     * Get current item based on the iterator index
     * @return FsItem
     */
    public function current(): FsItem
    {
        $key = $this->keys[$this->iteratorIndex];
        return $this->items[$key];
    }

    /**
     * Get current key based on the iterator index
     * @return string
     */
    public function key(): string
    {
        return $this->keys[$this->iteratorIndex];
    }

    /**
     * Increment iterator index
     * @return void
     */
    public function next(): void
    {
        ++$this->iteratorIndex;
    }

    /**
     * Reset iterator index
     * @return void
     */
    public function rewind(): void
    {
        $this->iteratorIndex = 0;
    }

    /**
     * Item exist at iterator index position
     * @return boolean
     */
    public function valid(): bool
    {
        return isset($this->keys[$this->iteratorIndex]);
    }

    /**
     * Add fs item
     * @param FsItem $item
     * @return self
     */
    public function add(FsItem $item)
    {
        $this->items[$item->getPath()] = $item;
        $this->keys = array_keys($this->items);
        return $this;
    }

    /**
     * Get common parent directory
     * If all items does not have the same parent return null
     * @return ?FsItem
     */
    public function getCommonParent(): ?Directory
    {
        $parent = null;

        foreach ($this->items as $item) {
            $itemParent = $item->getParent();

            if ($parent !== null && $itemParent->getPath() !== $parent) {
                return null;
            }
            $parent = $itemParent->getPath();
        }
        return $itemParent;
    }

    /**
     * Merge two collection
     * @param FsItemsCollection $collection
     * @return self
     */
    public function merge(FsItemsCollection $collection): self
    {
        foreach($collection as $item) {
            $this->add($item);
        }
        return $this;
    }
}
