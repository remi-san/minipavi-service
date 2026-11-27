<?php

declare(strict_types=1);

namespace MiniPaviService\Page\Parser;

use MiniPaviService\Message\MessageElement;
use MiniPaviService\Message\Messages;
use MiniPaviService\Message\MessageTexts;
use MiniPaviService\Page\Building\PageXmlException;
use MiniPaviService\Xml\XmlElements;
use MiniPaviService\Xml\XmlException;

/**
 * The messages of the pages: the service's, which a page redefines for itself with
 * <message name> at its top level
 *
 * @internal
 */
final readonly class PageMessages
{
    /**
     * @param Messages $messages the service's messages
     */
    public function __construct(
        private Messages $messages,
    ) {}

    /**
     * The messages of a <page> element: the service's, with the texts it redefines (messages of
     * the service only)
     *
     * @param string $name name of the page, in errors
     *
     * @throws PageXmlException for a <message> that is invalid, given twice, or not one of the
     *                          service's
     */
    public function of(\SimpleXMLElement $page, string $name): Messages
    {
        // One pass, in document order: the first error in the file is the one reported
        $texts = new MessageTexts(in: 'the page');
        try {
            foreach (XmlElements::children($page) as $tag => $element) {
                if ($tag !== 'message') {
                    continue;
                }
                $message = MessageElement::of($element, $name);
                if (!$this->messages->has($message->name)) {
                    throw $message->error("name=\"{$message->name}\" is not a message of the service");
                }
                $texts->add($message);
            }
        } catch (XmlException $e) {
            throw new PageXmlException($e->getMessage(), previous: $e);
        }

        return $this->messages->with($texts->texts());
    }
}
