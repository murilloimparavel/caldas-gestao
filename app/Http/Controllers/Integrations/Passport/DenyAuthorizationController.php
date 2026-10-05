<?php

namespace App\Http\Controllers\Integrations\Passport;

use App\Support\Integrations\OAuthContextBinding;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController as PassportDenyAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class DenyAuthorizationController extends PassportDenyAuthorizationController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly OAuthContextBinding $binding,
    ) {
        parent::__construct($server);
    }

    public function deny(Request $request, ResponseInterface $psrResponse): Response
    {
        try {
            return parent::deny($request, $psrResponse);
        } finally {
            $request->session()->forget('integration.oauth.context');
            $this->binding->clearExchange();
        }
    }
}
