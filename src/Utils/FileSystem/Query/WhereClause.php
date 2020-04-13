<?php
namespace PathMotion\CI\Utils\FileSystem\Query;

use LogicException;
use PathMotion\CI\Utils\FileSystem\FsItem;

/**
 * Where clause should help to filter/search FSItems in a collection
 */
class WhereClause
{

    /**
     * Where clause strict match
     * @var int
     */
    const MATCH_STRICT = 1;

    /**
     * Where clause contains match
     * @var int
     */
    const MATCH_CONTAINS = 2;

    /**
     * Where clause end with match
     * @var int
     */
    const MATCH_END_WITH = 3;

    /**
     * Method to call
     * @var string
     */
    private $method;

    /**
     * Element to compare
     * @var mixed
     */
    private $expected;

    /**
     * Match type
     * @var int
     */
    private $type = self::MATCH_STRICT;

    private function __construct(string $method, $expected, int $type = self::MATCH_STRICT)
    {
        $this->method = $method;
        $this->expected = $expected;
        $this->type = $type;
    }

    /**
     * Match with FsItem
     * @param FsItem $item
     * @return boolean
     */
    public function match(FsItem $item): bool
    {
        if (!is_callable([$item, $this->method])) {
            $error = sprintf('Method `%s` must be a callable of %s', $this->method, get_class($item));
            throw new LogicException($error);
        }
        $result = $item->{ $this->method }();

        switch ($this->type) {
            case self::MATCH_STRICT:
                return $result === $this->expected;
                break;

            case self::MATCH_CONTAINS:
                return strpos($result, $this->expected) !== false;
                break;

            case self::MATCH_END_WITH:
                return preg_match('~' . preg_quote($this->expected, '~') . '$~', $result);
                break;
        }
        return false;
    }

    /**
     * Is directory
     * @return WhereClause
     */
    public static function isDir(): WhereClause
    {
        return new WhereClause('isDir', true, self::MATCH_STRICT);
    }

    /**
     * Is not a directory
     * @return WhereClause
     */
    public static function isNotDir(): WhereClause
    {
        return new WhereClause('isDir', false, self::MATCH_STRICT);
    }

    /**
     * item name contains
     * @param string $query
     * @return WhereClause
     */
    public static function nameContains(string $query): WhereClause
    {
        return new WhereClause('baseName', $query, self::MATCH_CONTAINS);
    }

    /**
     * Item extension is
     * @param string $query
     * @return WhereClause
     */
    public static function extension(string $query): WhereClause
    {
        return new WhereClause('baseName', $query, self::MATCH_END_WITH);
    }
}
