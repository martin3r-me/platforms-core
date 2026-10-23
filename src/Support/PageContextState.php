<?php

namespace Platform\Core\Support;

/**
 * Seitenkontext des laufenden Requests (scoped).
 *
 * HasPageContext legt hier beim Rendern der Seite den `page-context`-Payload ab;
 * das Kontext-Panel im Layout wird danach gemountet und liest ihn direkt —
 * so ist das Panel schon im ersten HTML, ohne auf das Event warten zu müssen.
 */
class PageContextState
{
    public ?array $payload = null;
}
