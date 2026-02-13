<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Token;
use Laravel\Passport\TransientToken;
use Laravel\Passport\PersonalAccessTokenResult;

/**
 * HasApiTokens Trait für Passport Personal Access Tokens.
 *
 * Bietet Token-Management-Funktionen für User-Models:
 * - Token erstellen mit optionalem Ablaufdatum
 * - Aktive Tokens abrufen
 * - Einzelne oder alle Tokens widerrufen
 */
trait HasApiTokens
{
    /**
     * The current access token for the authentication user.
     *
     * @var \Laravel\Passport\Token|null
     */
    protected $accessToken;

    /**
     * Get all of the access tokens for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class, 'user_id')->orderBy('created_at', 'desc');
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param  string  $name
     * @param  array  $scopes
     * @param  \DateTimeInterface|null  $expiresAt
     * @return \Laravel\Passport\PersonalAccessTokenResult
     */
    public function createToken(string $name, array $scopes = ['*'], ?\DateTimeInterface $expiresAt = null): PersonalAccessTokenResult
    {
        $result = app(\Laravel\Passport\PersonalAccessTokenFactory::class)->make(
            $this->getKey(), $name, $scopes, $this->getProviderName()
        );

        // Set expiration date if provided
        if ($expiresAt !== null) {
            $result->token->expires_at = $expiresAt;
            $result->token->save();
        }

        return $result;
    }

    /**
     * Get all active (non-revoked) tokens for the user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function activeTokens()
    {
        return $this->tokens()
            ->where('revoked', false)
            ->get();
    }

    /**
     * Revoke a specific token by ID.
     *
     * @param  string|int  $tokenId
     * @return bool
     */
    public function revokeToken($tokenId): bool
    {
        $token = $this->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return false;
        }

        $token->revoked = true;
        return $token->save();
    }

    /**
     * Revoke all tokens for this user.
     *
     * @return int Number of revoked tokens
     */
    public function revokeAllTokens(): int
    {
        return $this->tokens()
            ->where('revoked', false)
            ->update(['revoked' => true]);
    }

    /**
     * Determine if the current API token has a given scope.
     *
     * @param  string  $scope
     * @return bool
     */
    public function tokenCan(string $scope): bool
    {
        return $this->accessToken && $this->accessToken->can($scope);
    }

    /**
     * Determine if the current API token is missing a given scope.
     *
     * @param  string  $scope
     * @return bool
     */
    public function tokenCant(string $scope): bool
    {
        return !$this->tokenCan($scope);
    }

    /**
     * Get the access token currently associated with the user.
     *
     * @return \Laravel\Passport\Token|null
     */
    public function currentAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Set the current access token for the user.
     *
     * @param  \Laravel\Passport\Token|null  $accessToken
     * @return $this
     */
    public function withAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Get all of the user's registered OAuth clients.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clients()
    {
        return $this->hasMany(\Laravel\Passport\Client::class, 'user_id');
    }

    /**
     * Create a new transient token for the user.
     *
     * @return $this
     */
    public function createTransientToken()
    {
        $this->accessToken = new TransientToken;

        return $this;
    }

    /**
     * Get the provider name for the user.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        // Find the provider that has this model configured
        foreach (config('auth.providers') as $provider => $config) {
            if (($config['driver'] ?? null) === 'eloquent' &&
                $this instanceof ($config['model'] ?? null)) {
                return $provider;
            }
        }

        // Default to 'users' provider
        return 'users';
    }
}
