<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StepUpPasskeyVerificationRequest;
use App\Support\Integrations\StepUpSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Support\WebAuthn;

class StepUpPasskeyController extends Controller
{
    public function options(Request $request, GenerateVerificationOptions $generate, StepUpSession $stepUp): JsonResponse
    {
        $stepUp->assertPurpose((string) $request->route('purpose'));
        $user = $request->user();

        abort_unless($user instanceof PasskeyUser && $user->hasPasskeysEnabled(), 403);

        $options = $generate($user);
        $ceremony = $stepUp->issueChallenge($request, $options, (string) $request->route('purpose'));

        return response()->json([
            'ceremony' => $ceremony,
            'options' => WebAuthn::toBrowserArray($options),
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function verify(
        StepUpPasskeyVerificationRequest $request,
        VerifyPasskey $verify,
        StepUpSession $stepUp,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user instanceof PasskeyUser, 403);

        $passkey = $verify($request->credential(), $request->verificationOptions(), $user);
        $stepUp->grant($request, (string) $request->route('purpose'), $passkey);

        return response()->json(['status' => 'passkey-step-up-verified'])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
