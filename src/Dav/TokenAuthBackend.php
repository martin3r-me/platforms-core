<?php

namespace Platform\Core\Dav;

use Platform\Core\Models\DavSubscription;
use Platform\Core\Services\TeamContext;
use Sabre\DAV\Auth\Backend\AbstractBasic;
use Sabre\HTTP\Auth\Basic;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * DAV-Auth über HTTP Basic: Passwort = {@see DavSubscription::$secret}.
 *
 * Der Benutzername wird ignoriert — das Abo wird eindeutig über das Secret
 * bestimmt. Bei Erfolg wird das Abo in den geteilten {@see DavContext} geschrieben
 * und der Team-Kontext gesetzt, damit alle nachgelagerten Scopes (Sichtbarkeit,
 * Billing) im richtigen Team laufen.
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class TokenAuthBackend extends AbstractBasic
{
    protected $realm = 'CRM CardDAV';

    public function __construct(
        private readonly DavContext $context,
        private readonly ?string $expectedHandle = null,
    ) {
    }

    protected function validateUserPass($username, $password): bool
    {
        if (empty($password)) {
            return false;
        }

        $query = DavSubscription::query()
            ->active()
            ->where('secret', $password);

        // Handle aus dem URL-Pfad muss zum Abo passen (jedes Abo = eigene URL).
        if ($this->expectedHandle !== null) {
            $query->where('handle', $this->expectedHandle);
        }

        $subscription = $query->first();

        if (! $subscription || ! $subscription->user) {
            return false;
        }

        TeamContext::set($subscription->team_id);

        $subscription->markUsed();

        $this->context->setSubscription($subscription);

        return true;
    }

    /**
     * Wie {@see AbstractBasic::check()}, aber mit deterministischem Principal
     * `principals/{userId}` — passend zum PrincipalBackend.
     */
    public function check(RequestInterface $request, ResponseInterface $response)
    {
        $auth = new Basic($this->realm, $request, $response);

        $userpass = $auth->getCredentials();
        if (! $userpass) {
            return [false, "No 'Authorization: Basic' header found. Either the client didn't send one, or the server is misconfigured"];
        }

        if (! $this->validateUserPass($userpass[0], $userpass[1])) {
            return [false, 'Username or password was incorrect'];
        }

        return [true, $this->principalPrefix.$this->context->subscription()->user_id];
    }
}
