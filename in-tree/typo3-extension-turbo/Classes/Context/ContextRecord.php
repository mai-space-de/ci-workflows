<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Context;

use Maispace\MaiTurbo\Context\Exception\TableNameNotFound;

class ContextRecord implements \JsonSerializable
{
    public function __construct(
        public readonly string $tableName,
        public readonly int $id,
        public readonly int $pageId,
    ) {
        if (!isset($GLOBALS['TCA'][$tableName])) {
            throw new TableNameNotFound(sprintf('Table name "%s" is invalid', $tableName), 1671023687);
        }
    }

    /**
     * @return array{tableName: string, id: int, pageId: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'tableName' => $this->tableName,
            'id' => $this->id,
            'pageId' => $this->pageId,
        ];
    }
}
