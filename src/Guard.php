<?php

namespace Laravel\Airlock;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Laravel\Airlock\HasApiTokens;

class Guard
{
    /**
     * The authentication factory implementation.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new guard instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Retrieve the authenticated user for the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function __invoke(Request $request)
    {
        if ($user = $this->auth->guard('web')->user()) {
            return $this->supportsTokens()
                        ? $user->withAccessToken(new TransientToken)
                        : $user;
        }

        if ($this->supportsTokens() && $token = $request->bearerToken()) {
            $model = Airlock::$personalAccessTokenModel;

            $accessToken = $model::where('token', hash('sha256', $token))->first();

            if (! $accessToken) {
                return;
            }

            return $accessToken->user->withAccessToken(
                tap($accessToken->forceFill(['last_used_at' => now()]))->save()
            );
        }
    }

    /**
     * Determine if the user model supports API tokens.
     *
     * @return bool
     */
    protected function supportsTokens()
    {
        return in_array(HasApiTokens::class, class_uses_recursive(
            $this->auth->guard('web')->getProvider()->getModel()
        ));
    }
}
