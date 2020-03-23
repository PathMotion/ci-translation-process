<?php
namespace PathMotion\CI\Utils\FileSystem;

use Exception;

class FsItem
{
    private $relativePath;

    /**
     * FileSystem Item path
     * @var string
     */
    private $path;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new Exception(sprintf('%s is not a valid file or directory', $path));
        }
        $this->relativePath = $path;
        $this->path = realpath($path);
    }

    /**
     * get file system item path
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * File System item is a directory
     * @return boolean
     */
    public function isDir(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Get file system base name
     * @param string $suffix
     * @return string
     */
    public function baseName(string $suffix = null): string
    {
        return basename($this->path, $suffix);
    }

    /**
     * Get parent directory
     * return null if current FsItem is the root directory
     * @return Directory|null
     */
    public function parent(): ?Directory
    {
        $parentPath = dirname($this->path);

        if ($parentPath === $this->path) {
            return null;
        }
        return new Directory($parentPath);
    }
}
