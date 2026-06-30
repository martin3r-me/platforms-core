<?php

namespace Platform\Core\Verbalization\Feed;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Platform\Core\Models\VerbalizationFeed;

/**
 * Public Atom-Endpoint fuer Verbalization-Feeds.
 *
 * Sicherheits-Modell: opake UUID in der URL. Keine Loginpflicht.
 * Wer den Token kennt, sieht den Feed — wie iCal-Links, GitHub-Atom-Tokens etc.
 * Rotation via core.verbalization.feeds.PUT(rotate_token=true) invalidiert
 * den alten Token.
 *
 * Headers:
 *   - Content-Type: application/atom+xml; charset=utf-8
 *   - X-Robots-Tag: noindex, nofollow (keine Suchmaschinen-Indizierung)
 *   - Cache-Control: public, max-age=300 (5 Min)
 *   - ETag / Last-Modified fuer Polling-Effizienz
 */
class FeedController
{
    public function __construct(
        protected FeedService $service,
        protected AtomFeedRenderer $renderer,
    ) {}

    public function __invoke(Request $request, string $token): Response
    {
        // .xml-Suffix aus dem Token entfernen, falls die Route es nicht abfaengt
        $token = preg_replace('/\.xml$/', '', $token);

        $feed = VerbalizationFeed::where('uuid', $token)
            ->where('is_active', true)
            ->first();

        if (! $feed) {
            return new Response('Feed not found.', 404, ['Content-Type' => 'text/plain']);
        }

        if ($feed->access !== 'public') {
            return new Response('Feed is not public.', 403, ['Content-Type' => 'text/plain']);
        }

        $items = $this->service->itemsForFeed($feed);
        $body = $this->renderer->render($feed, $items);

        $lastModified = $items->max('created_at') ?? $feed->updated_at ?? now();
        $etag = '"' . md5($feed->uuid . ':' . $lastModified->getTimestamp() . ':' . $items->count()) . '"';

        // ETag / If-None-Match — billiges 304 fuer Polling-Reader
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return (new Response('', 304))
                ->header('ETag', $etag)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return (new Response($body, 200))
            ->header('Content-Type', 'application/atom+xml; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'public, max-age=300')
            ->header('ETag', $etag)
            ->header('Last-Modified', $lastModified->toRfc7231String());
    }
}
