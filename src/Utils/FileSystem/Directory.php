<?php
namespace PathMotion\CI\Utils\FileSystem;

use Exception;
use PathMotion\CI\Utils\FileSystem\Query\WhereClause;

class Directory extends FsItem
{
    public function __construct(string $path)
    {
        parent::__construct($path);

        if (!$this->isDir()) {
            throw new Exception(sprintf('%s must be a valid directory', $path));
        }
    }

    /**
     * Find directories
     * @return DirectoriesCollection
     */
    public function findDirectories(WhereClause ...$queries): DirectoriesCollection
    {
        $collection = new DirectoriesCollection();
        $items = array_diff(scandir($this->getPath()), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = realpath($this->getPath() . DIRECTORY_SEPARATOR . $item);

            if (!is_dir($itemPath)) {
                continue;
            }
            $dirItem = new Directory($itemPath);
            $stop = false;
            foreach ($queries as  $query) {
                if (!$query->match($dirItem)) {
                    $stop = true;
                    break;
                }
            }
            if ($stop === true) {
                continue;
            }
            $collection->add($dirItem);
        }
        return $collection;
    }

    /**
     * Find in current directory
     * @param WhereClause ...$queries
     * @return FsItemsCollection
     */
    public function find(WhereClause ...$queries): FsItemsCollection
    {
        $collection = new FsItemsCollection();
        $items = array_diff(scandir($this->getPath()), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = realpath($this->getPath() . DIRECTORY_SEPARATOR . $item);
            $fsItem = new FsItem($itemPath);

            $stop = false;
            foreach ($queries as  $query) {
                if (!$query->match($fsItem)) {
                    $stop = true;
                    break;
                }
            }
            if ($stop === true) {
                continue;
            }
            $collection->add($fsItem);
        }
        return $collection;
    }

    /**
     * Find recursively from current directory
     * @param WhereClause ...$queries
     * @return FsItemsCollection
     */
    public function findRecursively(WhereClause ...$queries): FsItemsCollection
    {
        $collection = new FsItemsCollection();
        $items = array_diff(scandir($this->getPath()), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = realpath($this->getPath() . DIRECTORY_SEPARATOR . $item);
            if (is_dir($itemPath)) {
                $fsItem = new Directory($itemPath);
                $matchedItems = $fsItem->findRecursively(...$queries);
                $collection->merge($matchedItems);
            } else {
                $fsItem = new File($itemPath);
            }
            $skip = false;
            foreach ($queries as  $query) {
                if (!$query->match($fsItem)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip === true) {
                continue;
            }
            $collection->add($fsItem);
        }
        return $collection;
    }
}
