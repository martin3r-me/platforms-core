<?php

namespace Platform\Core\Http\Responses\Passport;

use Illuminate\Contracts\Support\Responsable;
use Laravel\Passport\Contracts\AuthorizationViewResponse as AuthorizationViewResponseContract;

class AuthorizationViewResponse implements AuthorizationViewResponseContract
{
    /**
     * The parameters to pass to the view.
     */
    protected array $parameters = [];

    /**
     * Set the parameters that should be passed to the view.
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
        return response()->view('platform::passport.authorize', $this->parameters);
    }
}
