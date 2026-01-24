<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Passport\Token;
use Laravel\Passport\TransientToken;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Contracts\HasAbilities;

/**
 * Kombinierter HasApiTokens Trait für Sanctum und Passport.
 *
 * Löst den Konflikt der $accessToken Property zwischen beiden Paketen.
 * - Passport: Für OAuth2 Server (Third-Party-Clients, Authorization Code Flow)
 * - Sanctum: Für einfache API-Tokens und SPA-Authentifizierung
 */
trait HasApiTokens
{
    /**
     * The current access token for the authentication user.
     * Kompatibel mit beiden Paketen durch dynamische Typisierung.
     *
     * @var \Laravel\Sanctum\Contracts\HasAbilities|\Laravel\Passport\Token|null
     */
    protected $accessToken;

    // ========================================
    // SANCTUM METHODS
    // ========================================

    /**
     * Get the access tokens that belong to the model (Sanctum).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function tokens(): MorphMany
    {
        // Standardmäßig Sanctum-Tokens zurückgeben
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /**
     * Create a new personal access token for the user (Sanctum).
     *
     * @param  string  $name
     * @param  array  $abilities
     * @param  \DateTimeInterface|null  $expiresAt
     * @return \Laravel\Sanctum\NewAccessToken
     */
    public function createToken(string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    protected function generateTokenString(): string
    {
        return sprintf(
            '%s%s%s',
            config('sanctum.token_prefix', ''),
            $tokenEntropy = \Illuminate\Support\Str::random(40),
            hash('crc32b', $tokenEntropy)
        );
    }

    /**
     * Determine if the current API token has a given scope (Sanctum).
     *
     * @param  string  $ability
     * @return bool
     */
    public function tokenCan(string $ability): bool
    {
        return $this->accessToken && $this->accessToken->can($ability);
    }

    /**
     * Determine if the current API token is missing a given scope (Sanctum).
     *
     * @param  string  $ability
     * @return bool
     */
    public function tokenCant(string $ability): bool
    {
        return ! $this->tokenCan($ability);
    }

    /**
     * Get the access token currently associated with the user (Sanctum/Passport).
     *
     * @return \Laravel\Sanctum\Contracts\HasAbilities|\Laravel\Passport\Token|null
     */
    public function currentAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Set the current access token for the user (Sanctum/Passport).
     *
     * @param  \Laravel\Sanctum\Contracts\HasAbilities|\Laravel\Passport\Token|null  $accessToken
     * @return $this
     */
    public function withAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    // ========================================
    // PASSPORT METHODS
    // ========================================

    /**
     * Get all of the user's registered OAuth clients (Passport).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clients()
    {
        return $this->hasMany(\Laravel\Passport\Client::class, 'user_id');
    }

    /**
     * Get all of the access tokens for the user (Passport).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function passportTokens()
    {
        return $this->hasMany(Token::class, 'user_id')->orderBy('created_at', 'desc');
    }

    /**
     * Create a new personal access token for the user (Passport).
     *
     * @param  string  $name
     * @param  array  $scopes
     * @return \Laravel\Passport\PersonalAccessTokenResult
     */
    public function passportCreateToken(string $name, array $scopes = [])
    {
        return app(\Laravel\Passport\PersonalAccessTokenFactory::class)->make(
            $this->getKey(), $name, $scopes
        );
    }

    /**
     * Determine if the current API token has a given scope (Passport).
     *
     * @param  string  $scope
     * @return bool
     */
    public function passportTokenCan(string $scope): bool
    {
        return $this->accessToken && $this->accessToken->can($scope);
    }

    /**
     * Determine if the current API token is missing a given scope (Passport).
     *
     * @param  string  $scope
     * @return bool
     */
    public function passportTokenCant(string $scope): bool
    {
        return ! $this->passportTokenCan($scope);
    }

    /**
     * Create a new transient token for the user (Passport).
     *
     * @return $this
     */
    public function createTransientToken()
    {
        $this->accessToken = new TransientToken;

        return $this;
    }
}
