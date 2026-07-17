<?php

namespace Platform\Core\Dav;

use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

/**
 * Read-only Principal-Backend: genau ein Principal — der User des aktiven Abos.
 *
 * Principal-URI: `principals/{userId}` (passend zu {@see TokenAuthBackend}).
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class PrincipalBackend implements BackendInterface
{
    public function __construct(
        private readonly DavContext $context,
    ) {
    }

    private function principalUri(): string
    {
        return 'principals/'.$this->context->subscription()->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function principal(): array
    {
        $subscription = $this->context->subscription();
        $user = $subscription->user;

        return [
            'uri' => $this->principalUri(),
            '{DAV:}displayname' => $user->name ?? ('User '.$subscription->user_id),
            '{http://sabredav.org/ns}email-address' => $user->email ?? null,
        ];
    }

    public function getPrincipalsByPrefix($prefixPath)
    {
        if (! str_starts_with($this->principalUri(), rtrim($prefixPath, '/').'/')) {
            return [];
        }

        return [$this->principal()];
    }

    public function getPrincipalByPath($path)
    {
        return $path === $this->principalUri() ? $this->principal() : null;
    }

    public function updatePrincipal($path, PropPatch $propPatch)
    {
        // Read-only: keine Änderungen übernehmen.
    }

    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        return [];
    }

    public function findByUri($uri, $principalPrefix)
    {
        $user = $this->context->subscription()->user;

        if ($user && $user->email && $uri === 'mailto:'.$user->email) {
            return $this->principalUri();
        }

        return null;
    }

    public function getGroupMemberSet($principal)
    {
        return [];
    }

    public function getGroupMembership($principal)
    {
        return [];
    }

    public function setGroupMemberSet($principal, array $members)
    {
        // Read-only: keine Gruppen.
    }
}
