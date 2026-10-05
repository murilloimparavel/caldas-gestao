@php
    $context = $request->attributes->get(\App\Support\TenantContext::class);
    $oauthContext = $request->session()->get('integration.oauth.context', []);
    $selectedCapabilities = is_array($oauthContext) && is_array($oauthContext['capabilities'] ?? null)
        ? array_values(array_map('strval', $oauthContext['capabilities']))
        : [];
    $capabilities = [
        'context:read' => 'Read whether this integration is authenticated and bound to the selected tenant and unit.',
        'catalog:read' => 'Read approved category and professional names, plus service names, prices, and durations.',
        'setup:read' => 'Read aggregate setup and booking readiness indicators for the selected unit.',
        'operations:propose' => 'Propose configuration changes for explicit administrator review and confirmation.',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Authorize integration - {{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>Authorize {{ $client->name }}</h1>
        <p>This application is requesting access to your Caldas Indica integration.</p>
        <p>Customers, contacts, notes, sales, and other private catalog fields are never available through this integration.</p>

        <section aria-labelledby="account-heading">
            <h2 id="account-heading">Selected administrative context</h2>
            <dl>
                <dt>Administrator</dt>
                <dd>{{ $user->email }}</dd>
                <dt>Tenant</dt>
                <dd>{{ $context?->tenant?->name ?? 'Selected tenant' }}</dd>
                <dt>Unit</dt>
                <dd>{{ $context?->unit?->name ?? 'All authorized units' }}</dd>
            </dl>
        </section>

        <form id="oauth-consent-form" method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <fieldset>
                <legend>Choose capabilities</legend>
                <p>Select at least one capability. The default is the least-privilege context read capability.</p>
                @forelse($capabilities as $capability => $description)
                    <label>
                        <input
                            type="checkbox"
                            name="capabilities[]"
                            value="{{ $capability }}"
                            @checked(in_array($capability, $selectedCapabilities, true))
                        >
                        <strong>{{ $capability }}</strong>
                        <span>{{ $description }}</span>
                    </label>
                @empty
                    <p>No approved capabilities were requested.</p>
                @endforelse
            </fieldset>

            <p id="oauth-step-up-status" role="status" aria-live="polite">Confirm these selected capabilities with a passkey before authorizing.</p>
            <button id="oauth-step-up-button" type="button">Confirm with passkey</button>
            <button id="oauth-consent-submit" type="submit" disabled>Authorize selected capabilities</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Deny</button>
        </form>
    </main>
    <script>
        (() => {
            const form = document.querySelector('#oauth-consent-form');
            const stepUpButton = document.querySelector('#oauth-step-up-button');
            const authorizeButton = document.querySelector('#oauth-consent-submit');
            const status = document.querySelector('#oauth-step-up-status');
            const clientId = form.querySelector('input[name="client_id"]').value;
            let verifiedBinding = null;

            const selectedCapabilities = () => Array.from(form.querySelectorAll('input[name="capabilities[]"]:checked'))
                .map((input) => input.value)
                .sort();
            const currentBinding = () => `${clientId}:${selectedCapabilities().join(',')}`;
            const base64UrlToBuffer = (value) => {
                const base64 = value.replace(/-/gu, '+').replace(/_/gu, '/') + '='.repeat((4 - value.length % 4) % 4);
                const binary = window.atob(base64);
                return Uint8Array.from(binary, (character) => character.charCodeAt(0)).buffer;
            };
            const bufferToBase64Url = (buffer) => {
                const bytes = new Uint8Array(buffer);
                let binary = '';
                bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
                return window.btoa(binary).replace(/\+/gu, '-').replace(/\//gu, '_').replace(/=+$/gu, '');
            };
            const sendJson = async (url, payload) => {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                if (!response.ok) {
                    throw new Error('Passkey confirmation failed. Restart OAuth authorization and try again.');
                }
                return response.json();
            };

            form.querySelectorAll('input[name="capabilities[]"]').forEach((input) => {
                input.addEventListener('change', () => {
                    authorizeButton.disabled = verifiedBinding !== currentBinding();
                });
            });

            stepUpButton.addEventListener('click', async () => {
                const capabilities = selectedCapabilities();
                if (capabilities.length === 0 || !navigator.credentials?.get || !('PublicKeyCredential' in window)) {
                    status.textContent = 'Select at least one capability and use a browser with passkey support.';
                    return;
                }

                stepUpButton.disabled = true;
                authorizeButton.disabled = true;
                status.textContent = 'Waiting for passkey confirmation…';

                try {
                    const optionsUrl = new URL(@json(route('integrations.step-up.options', ['purpose' => 'oauth.consent'])), window.location.origin);
                    optionsUrl.searchParams.set('client_id', clientId);
                    capabilities.forEach((capability) => optionsUrl.searchParams.append('capabilities[]', capability));
                    const optionsResponse = await fetch(optionsUrl, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!optionsResponse.ok) {
                        throw new Error('Could not start passkey verification. Ensure a passkey is registered, then restart OAuth authorization.');
                    }
                    const challenge = await optionsResponse.json();
                    const publicKey = {
                        ...challenge.options,
                        challenge: base64UrlToBuffer(challenge.options.challenge),
                        allowCredentials: challenge.options.allowCredentials?.map((credential) => ({
                            ...credential,
                            id: base64UrlToBuffer(credential.id),
                        })),
                    };
                    const assertion = await navigator.credentials.get({ publicKey });
                    if (!(assertion instanceof PublicKeyCredential)) {
                        throw new Error('Passkey confirmation was cancelled.');
                    }

                    const response = assertion.response;
                    await sendJson(@json(route('integrations.step-up.verify', ['purpose' => 'oauth.consent'])), {
                        client_id: clientId,
                        capabilities,
                        ceremony: challenge.ceremony,
                        credential: {
                            id: assertion.id,
                            rawId: bufferToBase64Url(assertion.rawId),
                            type: 'public-key',
                            response: {
                                authenticatorData: bufferToBase64Url(response.authenticatorData),
                                clientDataJSON: bufferToBase64Url(response.clientDataJSON),
                                signature: bufferToBase64Url(response.signature),
                                userHandle: response.userHandle === null ? null : bufferToBase64Url(response.userHandle),
                            },
                            clientExtensionResults: assertion.getClientExtensionResults(),
                        },
                    });

                    verifiedBinding = currentBinding();
                    authorizeButton.disabled = false;
                    status.textContent = 'Passkey confirmed for these exact capabilities. Authorize to continue.';
                } catch (error) {
                    status.textContent = error instanceof Error ? error.message : 'Passkey confirmation failed.';
                } finally {
                    stepUpButton.disabled = false;
                }
            });

            form.addEventListener('submit', (event) => {
                if (verifiedBinding !== currentBinding()) {
                    event.preventDefault();
                    authorizeButton.disabled = true;
                    status.textContent = 'Confirm the selected capabilities again with a passkey.';
                }
            });
        })();
    </script>
</body>
</html>
