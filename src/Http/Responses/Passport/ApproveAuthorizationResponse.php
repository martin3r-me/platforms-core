<?php

namespace Platform\Core\Http\Responses\Passport;

use Illuminate\Http\Response;
use Laravel\Passport\Contracts\ApproveAuthorizationResponse as ApproveAuthorizationResponseContract;

class ApproveAuthorizationResponse implements ApproveAuthorizationResponseContract
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
        // Redirect with authorization code
        $redirectUri = $this->parameters['redirect_uri'] ?? '/';
        $code = $this->parameters['auth_code'] ?? '';
        $state = $this->parameters['state'] ?? null;

        $query = http_build_query(array_filter([
            'code' => $code,
            'state' => $state,
        ]));

        return redirect($redirectUri . '?' . $query);
    }
}
