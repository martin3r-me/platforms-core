<?php

namespace Platform\Core\Content;

/**
 * Kanonisches Block-Vokabular der Content-Rendering-Schicht.
 *
 * DER PORT zwischen Core (Parser) und ui-tailwind (nx-Bausteine): Core emittiert
 * Blöcke dieses Typs, nx rendert sie. Beide Seiten kennen nur dieses Vokabular,
 * nicht einander. Design-agnostisch — hier stehen Rollen, keine Farben/CSS.
 */
enum BlockType: string
{
    case Heading = 'heading';       // level, inline_html, anchor
    case Paragraph = 'paragraph';   // inline_html
    case ListBlock = 'list';        // ordered(bool), items([inline_html])
    case Quote = 'quote';           // inline_html
    case Divider = 'divider';       // —
    case Callout = 'callout';       // variant, inline_html
    case Code = 'code';             // language?, code(raw)
    case Applet = 'applet';         // code(raw html/js)
    case Table = 'table';           // head, rows  (v1: via Html-Fallback)
    case Figure = 'figure';         // src, alt, caption?
    case Html = 'html';             // Fallback/Passthrough: html (bereits sicher gerendert)
}
