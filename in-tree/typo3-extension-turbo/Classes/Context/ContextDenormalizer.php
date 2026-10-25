<?php

declare(strict_types=1);

namespace Maispace\MaiTurbo\Context;

use Maispace\MaiTurbo\Turbo\Frame;

class ContextDenormalizer
{
    private const ATTRIBUTE_MAP = [
        'frame' => Frame::class,
    ];

    /**
     * @param array{renderingPath: string, contextRecord: array{tableName: string, id: int, pageId: int}, attributes?: array<string, mixed>} $data
     * @throws Exception\TableNameNotFound
     */
    public function denormalize(array $data): MaiTurboContext
    {
        $context = new MaiTurboContext(
            renderingPath: new RenderingPath($data['renderingPath']),
            contextRecord: new ContextRecord(...$data['contextRecord']),
        );
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $name => $attributeData) {
                if (isset(self::ATTRIBUTE_MAP[$name])) {
                    $className = self::ATTRIBUTE_MAP[$name];
                    $attribute = $className::denormalize($attributeData, ['context' => $context]);
                    $context = $context->withAttribute($name, $attribute);
                }
            }
        }

        return $context;
    }
}
