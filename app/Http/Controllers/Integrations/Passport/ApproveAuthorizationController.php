<?php

namespace App\Http\Controllers\Integrations\Passport;

use App\Support\Integrations\OAuthContextBinding;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class ApproveAuthorizationController extends PassportApproveAuthorizationController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly OAuthContextBinding $binding,
    ) {
        parent::__construct($server);
    }

    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        try {
            return parent::approve($request, $psrResponse);
        } finally {
            $request->session()->forget('integration.oauth.context');
            $this->binding->clearExchange();
        }
    }
}
