<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        if ($user) {
            $startingPage = $user->position?->starting_page ?? $user->employee?->position?->starting_page;

            if ($startingPage) {
                return redirect()->intended($startingPage);
            }
        }

        return redirect()->intended(config('fortify.home'));
    }
}
