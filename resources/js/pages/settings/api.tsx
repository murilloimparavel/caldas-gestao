import { Head } from '@inertiajs/react';
import IntegrationCredentials from '@/components/integration-credentials';
import type {
    Credential,
    OAuthGrant,
} from '@/components/integration-credentials';
import { api as edit } from '@/routes/settings';

export default function ApiSettings({
    canManageIntegrations,
    hasIntegrationPasskey,
    integrationCredentials,
    integrationOAuthGrants,
}: {
    canManageIntegrations: boolean;
    hasIntegrationPasskey: boolean;
    integrationCredentials: Credential[];
    integrationOAuthGrants: OAuthGrant[];
}) {
    return (
        <>
            <Head title="API e integrações" />

            <h1 className="sr-only">API e integrações</h1>

            <IntegrationCredentials
                canManage={canManageIntegrations}
                credentials={integrationCredentials}
                oauthGrants={integrationOAuthGrants}
                hasIntegrationPasskey={hasIntegrationPasskey}
            />
        </>
    );
}

ApiSettings.layout = {
    breadcrumbs: [
        {
            title: 'API e integrações',
            href: edit(),
        },
    ],
};
