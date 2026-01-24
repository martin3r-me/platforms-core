<?php

namespace Platform\Core\Http\Responses\Passport;

use Illuminate\Http\Response;
use Laravel\Passport\Contracts\DenyAuthorizationResponse as DenyAuthorizationResponseContract;

class DenyAuthorizationResponse implements DenyAuthorizationResponseContract
{
    /**
     * The authorization parameters.
     */
    protected array $parameters = [];

    /**
     * Set the parameters that should be passed to the response.
     */
    public function withParameters(array $parameters = []): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        $redirectUri = $this->parameters['redirect_uri'] ?? '/';
        $state = $this->parameters['state'] ?? null;

        $query = http_build_query(array_filter([
            'error' => 'access_denied',
            'error_description' => 'The resource owner denied the request.',
            'state' => $state,
        ]));

        return redirect($redirectUri . '?' . $query);
    }
}
