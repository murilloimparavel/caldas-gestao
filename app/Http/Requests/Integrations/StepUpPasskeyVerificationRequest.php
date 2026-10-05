<?php

namespace App\Http\Requests\Integrations;

use App\Support\Integrations\StepUpSession;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Webauthn\PublicKeyCredentialRequestOptions;

class StepUpPasskeyVerificationRequest extends PasskeyVerificationRequest
{
    public function verificationOptions(): PublicKeyCredentialRequestOptions
    {
        return app(StepUpSession::class)->consumeChallenge(
            $this,
            (string) $this->route('purpose'),
            (string) $this->input('ceremony'),
        );
    }

    /**
     * Get the validation rules that apply to this request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'ceremony' => ['required', 'string', 'uuid'],
        ]);
    }
}
