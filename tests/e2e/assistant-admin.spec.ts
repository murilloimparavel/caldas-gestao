import { expect, test } from '@playwright/test';
import type { ConsoleMessage, Page, Request, Response } from '@playwright/test';

const requiredEnvironment = [
    'PLAYWRIGHT_TEST_BASE_URL',
    'GROQ_ENDPOINT',
    'GROQ_API_KEY',
    'E2E_OWNER_EMAIL',
    'E2E_OWNER_PASSWORD',
    'E2E_COLLABORATOR_EMAIL',
    'E2E_COLLABORATOR_PASSWORD',
    'E2E_TENANT_NAME',
    'E2E_UNIT_NAME',
    'E2E_REJECTED_SERVICE_NAME',
    'E2E_APPROVED_SERVICE_NAME',
] as const;

const required = (name: (typeof requiredEnvironment)[number]): string => {
    const value = process.env[name]?.trim();

    if (!value) {
        throw new Error(
            `Missing required E2E environment variable ${name}; the assistant E2E suite refuses to run without its fixture contract.`,
        );
    }

    return value;
};

for (const name of requiredEnvironment) {
    required(name);
}

const baseUrl = new URL(required('PLAYWRIGHT_TEST_BASE_URL'));
const groqEndpoint = new URL(required('GROQ_ENDPOINT'));
const ownerEmail = required('E2E_OWNER_EMAIL');
const ownerPassword = required('E2E_OWNER_PASSWORD');
const collaboratorEmail = required('E2E_COLLABORATOR_EMAIL');
const collaboratorPassword = required('E2E_COLLABORATOR_PASSWORD');
const unitName = required('E2E_UNIT_NAME');
const rejectedServiceName = required('E2E_REJECTED_SERVICE_NAME');
const approvedServiceName = required('E2E_APPROVED_SERVICE_NAME');

const isLoopback = (hostname: string): boolean =>
    hostname === '127.0.0.1' ||
    hostname === 'localhost' ||
    hostname === '[::1]';

if (!isLoopback(baseUrl.hostname)) {
    throw new Error(
        `PLAYWRIGHT_TEST_BASE_URL must point to a local test server, received ${baseUrl.origin}.`,
    );
}

if (
    groqEndpoint.protocol !== 'http:' ||
    !isLoopback(groqEndpoint.hostname) ||
    groqEndpoint.pathname !== '/v1/chat/completions'
) {
    throw new Error(
        `GROQ_ENDPOINT must target the local fake provider at /v1/chat/completions, received ${groqEndpoint.toString()}.`,
    );
}

if (!required('GROQ_API_KEY').startsWith('e2e-')) {
    throw new Error(
        'GROQ_API_KEY must be an ephemeral e2e-* value; real provider keys are forbidden.',
    );
}

type RuntimeGuard = {
    consoleErrors: string[];
    pageErrors: string[];
    failedRequests: string[];
    serverErrors: string[];
    unexpectedBrowserRequests: string[];
    directProviderRequests: string[];
};

const runtimeGuards = new WeakMap<Page, RuntimeGuard>();

const watchRuntime = (page: Page): void => {
    const guard: RuntimeGuard = {
        consoleErrors: [],
        pageErrors: [],
        failedRequests: [],
        serverErrors: [],
        unexpectedBrowserRequests: [],
        directProviderRequests: [],
    };

    runtimeGuards.set(page, guard);
    page.on('console', (message: ConsoleMessage) => {
        if (
            message.type() === 'error' &&
            !message.text().includes('ERR_BLOCKED_BY_CLIENT.Inspector')
        ) {
            guard.consoleErrors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        guard.pageErrors.push(error.message);
    });
    page.on('requestfailed', (request: Request) => {
        const requestUrl = new URL(request.url());

        if (requestUrl.origin !== baseUrl.origin) {
            return;
        }

        guard.failedRequests.push(
            `${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`.trim(),
        );
    });
    page.on('request', (request: Request) => {
        const requestUrl = new URL(request.url());

        if (
            requestUrl.origin === groqEndpoint.origin &&
            requestUrl.pathname === groqEndpoint.pathname
        ) {
            guard.directProviderRequests.push(
                `${request.method()} ${request.url()}`,
            );
        }

        if (
            !['data:', 'blob:'].includes(requestUrl.protocol) &&
            requestUrl.origin !== baseUrl.origin
        ) {
            guard.unexpectedBrowserRequests.push(
                `${request.method()} ${request.url()}`,
            );
        }
    });
    page.on('response', (response: Response) => {
        if (response.status() >= 500) {
            guard.serverErrors.push(
                `${response.status()} ${response.request().method()} ${response.url()}`,
            );
        }
    });
};

test.beforeAll(async () => {
    const healthUrl = new URL('/health', groqEndpoint.origin);
    const response = await fetch(healthUrl, {
        signal: AbortSignal.timeout(5_000),
    }).catch((error: unknown) => {
        throw new Error(
            `The local Groq fake provider is unavailable at ${healthUrl}: ${error instanceof Error ? error.message : String(error)}`,
        );
    });

    if (!response.ok) {
        throw new Error(
            `The local Groq fake provider returned HTTP ${response.status} at ${healthUrl}.`,
        );
    }

    const payload = (await response.json()) as {
        provider?: unknown;
        status?: unknown;
    };
    expect(payload.provider).toBe('caldas-e2e-groq-fake');
    expect(payload.status).toBe('ok');
});

test.beforeEach(async ({ page }) => {
    watchRuntime(page);
    await page.route('**/*', async (route) => {
        const requestUrl = new URL(route.request().url());

        if (
            ['data:', 'blob:'].includes(requestUrl.protocol) ||
            requestUrl.origin === baseUrl.origin
        ) {
            await route.continue();

            return;
        }

        await route.abort('blockedbyclient');
    });
});

test.afterEach(async ({ page }) => {
    const guard = runtimeGuards.get(page);

    expect(guard?.consoleErrors ?? [], 'Browser console errors').toEqual([]);
    expect(guard?.pageErrors ?? [], 'Browser page errors').toEqual([]);
    expect(guard?.failedRequests ?? [], 'Failed browser requests').toEqual([]);
    expect(guard?.serverErrors ?? [], 'HTTP 5xx responses').toEqual([]);
    expect(
        guard?.unexpectedBrowserRequests ?? [],
        'Browser requests outside the local application origin',
    ).toEqual([]);
    expect(
        guard?.directProviderRequests ?? [],
        'Browser must never call the Groq provider directly',
    ).toEqual([]);
});

const login = async (
    page: Page,
    email: string,
    password: string,
): Promise<void> => {
    const response = await page.goto('/login', {
        waitUntil: 'domcontentloaded',
    });
    expect(response?.status(), 'Login page response').toBe(200);
    await page.getByLabel('Email address').fill(email);
    await page
        .getByRole('textbox', { name: 'Password', exact: true })
        .fill(password);
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/\/dashboard(?:\?.*)?$/);
};

const openAssistantFromSidebar = async (page: Page): Promise<void> => {
    const isMobile =
        (page.viewportSize()?.width ?? Number.POSITIVE_INFINITY) < 768;

    if (isMobile) {
        const response = await page.goto('/assistant', {
            waitUntil: 'domcontentloaded',
        });
        expect(response?.status(), 'Admin assistant response').toBe(200);
    } else {
        const navigation = page.getByRole('navigation', {
            name: 'Navegação principal',
        });

        await expect(navigation).toBeVisible();
        await navigation.getByRole('button', { name: /Configurações/ }).click();
        const assistantLink = navigation.getByRole('link', {
            name: 'Assistente IA',
            exact: true,
        });
        await expect(assistantLink).toBeVisible();
        await assistantLink.click();
    }

    await expect(page).toHaveURL(/\/assistant$/);
    await expect(page.getByText(unitName, { exact: true })).toBeVisible();
    await expect(
        page.getByText('Assistente disponível', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Privacidade e uso de dados' }),
    ).toBeVisible();
    await expect(
        page.getByText(
            /conteúdo desta conversa e o contexto aprovado.*transmitidos à Groq para inferência.*histórico fica retido na aplicação Caldas por até\s+\d+\s+dias.*prazo é separado da retenção da Groq.*depende da conta, dos controles disponíveis e dos termos do provedor/i,
        ),
    ).toBeVisible();
    await expect(
        page.getByRole('link', {
            name: 'informações oficiais da Groq sobre uso de dados',
        }),
    ).toHaveAttribute('href', 'https://console.groq.com/docs/your-data');
    await expect(
        page.getByText(/Histórico na aplicação Caldas retido por \d+ dias/),
    ).toBeVisible();
    await expect(
        page.getByText(
            /ocultação automática identifica apenas alguns padrões comuns e não é completa.*Não informe dados de clientes, dados pessoais ou informações sensíveis/i,
        ),
    ).toBeVisible();
};

const openSecuritySettings = async (page: Page): Promise<void> => {
    const response = await page.goto('/settings/security', {
        waitUntil: 'domcontentloaded',
    });
    expect(response?.status(), 'Security settings response').toBe(200);

    if (
        /\/user\/confirm-password(?:\?.*)?$/.test(
            new URL(page.url()).pathname + new URL(page.url()).search,
        )
    ) {
        await page.getByLabel('Password', { exact: true }).fill(ownerPassword);
        await page.locator('[data-test="confirm-password-button"]').click();
        await expect(page).toHaveURL(/\/settings\/security(?:\?.*)?$/);
    }

    await expect(page.getByRole('heading', { name: 'Passkeys' })).toBeVisible();
};

const confirmPassword = async (page: Page): Promise<void> => {
    const response = await page.goto('/user/confirm-password', {
        waitUntil: 'domcontentloaded',
    });
    expect(response?.status(), 'Password confirmation response').toBe(200);
    await page.getByLabel('Password', { exact: true }).fill(ownerPassword);
    await page.locator('[data-test="confirm-password-button"]').click();
    await expect(page).toHaveURL(/\/dashboard(?:\?.*)?$/);
};

const installVirtualPasskey = async (page: Page): Promise<void> => {
    const cdp = await page.context().newCDPSession(page);

    await cdp.send('WebAuthn.enable');
    await cdp.send('WebAuthn.addVirtualAuthenticator', {
        options: {
            protocol: 'ctap2',
            transport: 'internal',
            hasResidentKey: true,
            hasUserVerification: true,
            isUserVerified: true,
            automaticPresenceSimulation: true,
        },
    });
};

test.describe('Internal assistant administrator boundary', () => {
    test('admin registers a passkey, approves a service proposal, and sees the service in the catalog', async ({
        page,
    }) => {
        const passkeyName = `E2E virtual authenticator ${crypto.randomUUID()}`;
        const serviceName = `${approvedServiceName} ${crypto.randomUUID()}`;

        await login(page, ownerEmail, ownerPassword);
        await installVirtualPasskey(page);

        await openSecuritySettings(page);
        await confirmPassword(page);
        await openSecuritySettings(page);
        await page.getByRole('button', { name: 'Adicionar passkey' }).click();
        await page.getByLabel('Nome da passkey').fill(passkeyName);
        await page.getByRole('button', { name: 'Cadastrar passkey' }).click();
        await expect(page.getByText(passkeyName, { exact: true })).toBeVisible({
            timeout: 15_000,
        });

        await openAssistantFromSidebar(page);
        await page
            .getByRole('button', { name: 'Nova conversa', exact: true })
            .click();
        await expect(page).toHaveURL(
            /\/assistant\/conversations\/[0-9a-f-]+$/i,
        );

        const composer = page.getByRole('textbox', {
            name: 'Mensagem para o assistente',
        });
        await expect(composer).toBeEnabled();
        await composer.fill(
            `Proponha o serviço "${serviceName}" e peça minha aprovação para criar o cadastro.`,
        );
        await page
            .getByRole('button', { name: 'Enviar mensagem', exact: true })
            .click();

        await expect(
            page.getByText('Proposta pendente de revisão', { exact: true }),
        ).toBeVisible({ timeout: 20_000 });
        await page
            .getByRole('link', { name: 'Revisar proposta', exact: true })
            .click();
        await expect(page).toHaveURL(
            /\/settings\/integrations\/proposals\/[0-9a-f-]+$/i,
        );
        await expect(
            page.getByText('Confira o que será criado', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Aguardando decisão', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText(serviceName, { exact: true }),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Confirmar criação', exact: true })
            .click();
        await expect(
            page.getByRole('alert').getByText('Concluída', { exact: true }),
        ).toBeVisible({ timeout: 20_000 });

        const servicesResponse = await page.goto(
            `/services?search=${encodeURIComponent(serviceName)}`,
            { waitUntil: 'domcontentloaded' },
        );
        expect(
            servicesResponse?.status(),
            'Approved service page response',
        ).toBe(200);
        await expect(
            page.getByText(serviceName, { exact: true }),
        ).toBeVisible();
    });

    test('admin creates a pending proposal, rejects it, and leaves the service absent', async ({
        page,
    }) => {
        await login(page, ownerEmail, ownerPassword);
        await openAssistantFromSidebar(page);

        await page
            .getByRole('button', { name: 'Nova conversa', exact: true })
            .click();
        await expect(page).toHaveURL(
            /\/assistant\/conversations\/[0-9a-f-]+$/i,
        );
        await expect(page.getByText(unitName, { exact: true })).toBeVisible();

        const composer = page.getByRole('textbox', {
            name: 'Mensagem para o assistente',
        });
        await expect(composer).toBeEnabled();
        await composer.fill('Proponha um serviço de teste E2E para revisão.');
        await page
            .getByRole('button', { name: 'Enviar mensagem', exact: true })
            .click();

        await expect(
            page.getByText('Proposta pendente de revisão', { exact: true }),
        ).toBeVisible({
            timeout: 20_000,
        });
        await page
            .getByRole('link', { name: 'Revisar proposta', exact: true })
            .click();
        await expect(page).toHaveURL(
            /\/settings\/integrations\/proposals\/[0-9a-f-]+$/i,
        );
        await expect(
            page.getByText('Confira o que será criado', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Aguardando decisão', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText(rejectedServiceName, { exact: true }).first(),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Rejeitar proposta', exact: true })
            .click();
        await expect(
            page.getByRole('alert').getByText('Rejeitada', { exact: true }),
        ).toBeVisible({ timeout: 15_000 });

        const servicesResponse = await page.goto(
            `/services?search=${encodeURIComponent(rejectedServiceName)}`,
            { waitUntil: 'domcontentloaded' },
        );
        expect(
            servicesResponse?.status(),
            'Filtered services page response',
        ).toBe(200);
        await expect(
            page.getByText(rejectedServiceName, { exact: true }),
        ).toHaveCount(0);
        await expect(
            page.getByText(
                /Nenhum serviço encontrado|Nenhum serviço cadastrado/i,
            ),
        ).toBeVisible();
    });

    test('collaborator cannot see or open the assistant', async ({ page }) => {
        await login(page, collaboratorEmail, collaboratorPassword);

        const isMobile =
            (page.viewportSize()?.width ?? Number.POSITIVE_INFINITY) < 768;

        if (!isMobile) {
            const navigation = page.getByRole('navigation', {
                name: 'Navegação principal',
            });
            await expect(navigation).toBeVisible();
            await expect(
                navigation.getByRole('link', {
                    name: 'Assistente IA',
                    exact: true,
                }),
            ).toHaveCount(0);
        }

        const response = await page.request.get('/assistant');
        expect(response?.status(), 'Collaborator assistant response').toBe(403);

        const conversationResponse = await page.request.get(
            `/assistant/conversations/${crypto.randomUUID()}`,
        );
        expect(
            conversationResponse?.status(),
            'Collaborator nonexistent assistant conversation response',
        ).toBe(404);

        const proposalResponse = await page.request.get(
            `/settings/integrations/proposals/${crypto.randomUUID()}`,
        );
        expect(
            proposalResponse?.status(),
            'Collaborator proposal review response',
        ).toBe(404);
    });
});
