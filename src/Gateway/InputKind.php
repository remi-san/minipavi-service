<?php

declare(strict_types=1);

namespace MiniPaviService\Gateway;

/**
 * Kind of field an InputCommand reads
 */
enum InputKind: string
{
    // One line, which every validWith key validates (MiniPavi's InputTxt)
    case Text = 'text';
    // Several lines, between which Suite and Retour move, the other validWith keys validating
    // (MiniPavi's InputMsg)
    case Message = 'message';
}
