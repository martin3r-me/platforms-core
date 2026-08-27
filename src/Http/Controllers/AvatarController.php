<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Platform\Core\Models\User;

/**
 * Liefert User-Avatare als kleines, cachebares WebP-Thumbnail — statt den rohen
 * base64-Data-URI (bis ~800 KB) in jedes Livewire-HTML einzubetten. Das Thumbnail
 * wird einmalig aus der `avatar`-Spalte erzeugt und auf Disk gecacht (Schlüssel =
 * Inhalts-Hash), danach mit ETag + Revalidierung ausgeliefert. Vgl. User::avatarUrl().
 */
class AvatarController extends Controller
{
    /** Kantenlänge des Thumbnails (deckt Retina für die kleinen UI-Avatare ab). */
    private const SIZE = 128;

    public function show(Request $request, User $user)
    {
        $raw = $user->avatar;
        if (! $raw) {
            abort(404);
        }

        // Externe URLs gehören nicht hierher (avatarUrl() gibt die direkt zurück).
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return redirect()->away($raw);
        }

        $hash = substr(sha1($raw), 0, 16);
        $etag = '"'.$hash.'"';

        // Conditional GET: unverändert → 304 ohne Body.
        if (trim($request->headers->get('If-None-Match', ''), '"') === $hash) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => 'private, max-age=86400']);
        }

        $cachePath = "avatars/{$user->id}-{$hash}.webp";
        $disk = Storage::disk('local');

        if (! $disk->exists($cachePath)) {
            $binary = $this->decodeBase64($raw);
            if ($binary === null) {
                abort(404);
            }
            try {
                $image = (new ImageManager(new Driver()))->read($binary);
                $image->scaleDown(self::SIZE, self::SIZE);
                $disk->put($cachePath, (string) $image->encode(new WebpEncoder(85)));
            } catch (\Throwable $e) {
                abort(404);
            }
        }

        return response($disk->get($cachePath), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => $etag,
        ]);
    }

    /** Dekodiert einen data:-URI oder rohen base64-String zu Binärdaten. */
    private function decodeBase64(string $raw): ?string
    {
        if (str_starts_with($raw, 'data:')) {
            $comma = strpos($raw, ',');
            if ($comma === false) {
                return null;
            }
            $raw = substr($raw, $comma + 1);
        }

        $binary = base64_decode($raw, true);

        return $binary !== false && $binary !== '' ? $binary : null;
    }
}
