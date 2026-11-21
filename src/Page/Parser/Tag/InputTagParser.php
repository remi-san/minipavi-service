<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser\Tag;

use MiniPaviService\Minitel\FunctionKey;
use MiniPaviService\Minitel\InputField;
use MiniPaviService\Minitel\Span;
use MiniPaviService\Page\Building\AttributeValues;
use MiniPaviService\Page\Building\PageXmlException;

/**
 * <input row col length lines validwith/>: the field of the page, once, at its top level, and
 * without {{placeholders}}
 */
final readonly class InputTagParser implements TagParser
{
    public function parse(XmlTag $tag, TagPosition $position, NodesParser $nodes): ParsedNodes
    {
        return ParsedNodes::input($tag->check(static function () use ($tag, $position): InputField {
            if (!$position->topLevel) {
                throw new PageXmlException('only at the top level of the page');
            }
            if ($tag->hasPlaceholder()) {
                throw new PageXmlException('no {{placeholders}} in <input>');
            }

            return self::field($tag->values());
        }));
    }

    /**
     * @throws PageXmlException for an invalid attribute
     */
    private static function field(AttributeValues $attributes): InputField
    {
        $keys = preg_split('/\s+/', trim($attributes->string('validwith', default: 'envoi')));
        if ($keys === false) {
            throw new PageXmlException('validwith: ' . preg_last_error_msg());
        }
        // Keys for the validwith attribute of <input>, separated by spaces
        $validWith = [];
        foreach ($keys as $name) {
            $validWith[] = FunctionKey::fromXmlName($name) ?? throw new PageXmlException(
                "unknown validwith key \"{$name}\" ("
                . implode(', ', array_map(static fn(FunctionKey $key) => $key->xmlName(), FunctionKey::cases()))
                . ')',
            );
        }

        return new InputField(
            new Span($attributes->position(defaultRow: 13), $attributes->optionalInt('length')),
            lines: $attributes->int('lines', default: 2),
            validWith: $validWith,
        );
    }
}
