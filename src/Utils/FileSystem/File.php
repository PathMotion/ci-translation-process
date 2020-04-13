<?php
namespace PathMotion\CI\Utils\FileSystem;

use Exception;

/**
 * File class represent a File in a file system
 */
class File extends FsItem
{
    public function __construct(string $path)
    {
        parent::__construct($path);

        if ($this->isDir()) {
            throw new Exception(sprintf('%s must be a valid file', $path));
        }
    }
}
