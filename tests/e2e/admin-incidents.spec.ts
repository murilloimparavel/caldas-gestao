import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const incidentId = 'fdfb5b31-42c1-409d-b491-0c3f83bb5249';
const options = {
    classification: { backend: 'Backend' },
    module: { booking: 'Agendamento' },
    severity: {
        critical: 'Crítica',
        high: 'Alta',
        medium: 'Média',
        low: 'Baixa',
    },
    workflow_status: {
        new: 'Novo',
        triaged: 'Em triagem',
        in_analysis: 'Em análise',
        proposal_ready: 'Proposta preparada',
        in_validation: 'Em validação',
        resolved: 'Resolvido',
        ignored: 'Ignorado',
    },
    reason: {
        triage_confirmed: 'Triagem confirmada',
        owner_assigned: 'Responsável definido',
        not_actionable: 'Sem ação necessária',
    },
    transitions: {
        new: ['triaged', 'ignored'],
        triaged: ['in_analysis', 'ignored'],
    },
};

// Backend permissions, filters, audit and concurrency have real HTTP coverage in
// AdminTelemetryIncidentsTest. This fixture isolates responsive UI without promoting
// the shared booking owner to a platform administrator.
async function openIncidentPanel(page: Page) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('e2e-booking@caldas.local');
    await page.locator('input[name="password"]').fill('CaldasE2E!2026');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    const source = await page.request.get('/online-booking', {
        headers: { Accept: 'text/html' },
    });
    const html = await source.text();
    const encoded = html.match(
        /<script\b[^>]*\bdata-page\b[^>]*>([\s\S]*?)<\/script>/,
    )?.[1];

    if (!encoded) {
        throw new Error('Missing Inertia page fixture');
    }

    const template = JSON.parse(encoded);
    const owner = {
        id: template.props.auth.user.id,
        name: 'Administrador de teste',
    };
    let incident = {
        id: incidentId,
        title: 'Falha no agendamento',
        classification: 'backend',
        module: 'booking',
        severity: 'high',
        confidence: 90,
        impact_summary: 'Um fluxo de agendamento pode estar indisponível.',
        workflow_status: 'new',
        owner_user_id: null as string | null,
        owner: null as typeof owner | null,
        lock_version: 0,
        detection_source: 'server',
        release: 'a'.repeat(40),
        occurrences: 15,
        status: 'completed',
        result: 'completed',
        attempts: 1,
        first_seen_at: '2026-10-29T10:00:00Z',
        last_seen_at: '2026-10-29T11:00:00Z',
        resolved_at: null,
        category: 'server_exception',
        exception_class: 'TypeError',
        route_name: 'public_booking.slug',
        auto_diagnosis_eligible: true,
        diagnosis_threshold: 1,
        http_status: null,
        frames: [{ file: 'app/Support/Example.php', line: 42 }],
        history: [] as {
            id: string;
            event_type: string;
            from_status: string;
            to_status: string;
            reason: string;
            actor: string;
            created_at: string;
        }[],
    };
    let mutation: Record<string, unknown> | null = null;
    await page.route('**/admin/incidents**', async (route) => {
        const url = new URL(route.request().url());
        const isDetail = url.pathname.endsWith(incidentId);
        const isMutation = ['PATCH', 'POST'].includes(route.request().method());

        if (isMutation) {
            mutation = route.request().postDataJSON();
            const status = String(mutation?.workflow_status);
            incident = {
                ...incident,
                workflow_status: status,
                lock_version: 1,
                owner_user_id: mutation?.owner_user_id as string | null,
                owner: mutation?.owner_user_id ? owner : null,
                history: [
                    {
                        id: 'audit-event',
                        event_type: 'workflow_transition',
                        from_status: 'new',
                        to_status: status,
                        reason: String(mutation?.reason),
                        actor: owner.name,
                        created_at: '2026-10-29T11:30:00Z',
                    },
                ],
            };
        }

        const props = isDetail
            ? { incident, owners: [owner], options }
            : {
                  incidents: {
                      data: [incident],
                      current_page: 1,
                      last_page: 1,
                      total: 1,
                      per_page: 20,
                      links: [],
                  },
                  filters: Object.fromEntries(url.searchParams),
                  metrics: { total: 1, open: 1, critical: 1, resolved: 0 },
                  options,
              };
        const payload = {
            ...template,
            component: `admin/incidents/${isDetail ? 'show' : 'index'}`,
            url: url.pathname + url.search,
            props: {
                ...template.props,
                ...props,
                flash: {
                    ...template.props.flash,
                    success: isMutation
                        ? 'Acompanhamento do incidente atualizado.'
                        : null,
                    error: null,
                },
            },
        };

        if (route.request().headers()['x-inertia']) {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                headers: { 'X-Inertia': 'true' },
                body: JSON.stringify(payload),
            });
        } else {
            const body = html.replace(
                /(<script\b[^>]*\bdata-page\b[^>]*>)([\s\S]*?)(<\/script>)/,
                (_match, open: string, _json: string, close: string) =>
                    `${open}${JSON.stringify(payload).replaceAll('<', '\\u003c')}${close}`,
            );
            await route.fulfill({
                status: 200,
                contentType: 'text/html',
                body,
            });
        }
    });
    await page.goto('/admin/incidents');

    return { owner, mutation: () => mutation };
}

for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'iPad', width: 820, height: 1180 },
]) {
    test(`central incidents supports filters and audited triage on ${viewport.name}`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize(viewport);
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));
        const panel = await openIncidentPanel(page);
        await expect(
            page.getByRole('heading', {
                name: 'Central de incidentes',
                exact: true,
            }),
        ).toBeVisible();
        await page
            .getByRole('combobox', { name: 'Módulo', exact: true })
            .selectOption('booking');
        await page.getByRole('button', { name: 'Filtrar incidentes' }).click();
        await expect(page).toHaveURL(/module=booking/);
        await page
            .getByRole('link', { name: 'Falha no agendamento', exact: true })
            .click();
        await expect(
            page.getByText('Acompanhamento humano', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Preparação concluída', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Salvar acompanhamento' }),
        ).toBeDisabled();
        await expect(
            page
                .getByRole('combobox', { name: 'Acompanhamento', exact: true })
                .locator('option[value="resolved"]'),
        ).toHaveCount(0);
        await page
            .getByRole('combobox', { name: 'Acompanhamento', exact: true })
            .selectOption('triaged');
        await page
            .getByRole('combobox', { name: 'Responsável', exact: true })
            .selectOption(panel.owner.id);
        await page
            .getByRole('button', { name: 'Salvar acompanhamento' })
            .click();
        await expect(page.getByRole('status')).toContainText(
            'Acompanhamento do incidente atualizado.',
        );
        expect(panel.mutation()).toMatchObject({
            workflow_status: 'triaged',
            owner_user_id: panel.owner.id,
            reason: 'triage_confirmed',
            lock_version: 0,
        });
        await expect(
            page.getByText('Triagem confirmada', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('app/Support/Example.php:42', { exact: true }),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        expect(errors).toEqual([]);
        await page.screenshot({
            path: `/tmp/caldas-admin-incidents-${viewport.name}-${testInfo.project.name}.png`,
            fullPage: true,
        });
    });
}
