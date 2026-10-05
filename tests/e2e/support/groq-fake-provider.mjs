import { Buffer } from 'node:buffer';
import http from 'node:http';
import process from 'node:process';

const host = process.env.GROQ_FAKE_HOST ?? '127.0.0.1';
const port = Number.parseInt(process.env.GROQ_FAKE_PORT ?? '8787', 10);
const rejectedServiceName = process.env.E2E_REJECTED_SERVICE_NAME;
const approvedServiceName = process.env.E2E_APPROVED_SERVICE_NAME;
const apiKey = process.env.GROQ_API_KEY;

if (!rejectedServiceName || !approvedServiceName) {
    throw new Error(
        'E2E_REJECTED_SERVICE_NAME and E2E_APPROVED_SERVICE_NAME are required by the Groq fake provider.',
    );
}

if (!apiKey || !apiKey.startsWith('e2e-')) {
    throw new Error(
        'GROQ_API_KEY must be an ephemeral e2e-* value for the fake provider.',
    );
}

const json = (response, status, payload) => {
    response.writeHead(status, {
        'content-type': 'application/json; charset=utf-8',
        'cache-control': 'no-store',
    });
    response.end(JSON.stringify(payload));
};

const requestedServiceName = (content) =>
    content.match(/serviço\s+"([^"]+)"/iu)?.[1] ?? null;

const readBody = async (request) => {
    const chunks = [];

    for await (const chunk of request) {
        chunks.push(chunk);
    }

    const raw = Buffer.concat(chunks).toString('utf8');

    return raw === '' ? {} : JSON.parse(raw);
};

const server = http.createServer(async (request, response) => {
    if (request.method === 'GET' && request.url === '/health') {
        json(response, 200, { provider: 'caldas-e2e-groq-fake', status: 'ok' });

        return;
    }

    if (request.method !== 'POST' || request.url !== '/v1/chat/completions') {
        json(response, 404, { error: 'not_found' });

        return;
    }

    if (request.headers.authorization !== `Bearer ${apiKey}`) {
        json(response, 401, { error: 'invalid_fake_provider_key' });

        return;
    }

    if (request.headers.origin) {
        json(response, 403, {
            error: 'browser_direct_provider_access_forbidden',
        });

        return;
    }

    try {
        const payload = await readBody(request);
        const messages = Array.isArray(payload.messages)
            ? payload.messages
            : [];
        const lastMessage = messages.at(-1);

        if (!lastMessage || typeof lastMessage.role !== 'string') {
            json(response, 400, { error: 'messages_required' });

            return;
        }

        if (lastMessage.role === 'user') {
            const userContent =
                typeof lastMessage.content === 'string'
                    ? lastMessage.content
                    : '';
            const isApprovalJourney = /aprova(?:r|ção)/iu.test(userContent);
            const serviceName = isApprovalJourney
                ? (requestedServiceName(userContent) ?? approvedServiceName)
                : rejectedServiceName;

            json(response, 200, {
                id: 'e2e-fake-completion-tool-call',
                object: 'chat.completion',
                choices: [
                    {
                        index: 0,
                        finish_reason: 'tool_calls',
                        message: {
                            role: 'assistant',
                            content: null,
                            tool_calls: [
                                {
                                    id: 'e2e-fake-propose-service',
                                    type: 'function',
                                    function: {
                                        name: 'propose_operation',
                                        arguments: JSON.stringify({
                                            operation: 'service.create',
                                            input: {
                                                name: serviceName,
                                                duration_minutes: 30,
                                                price_cents: 5000,
                                                status: 'active',
                                            },
                                        }),
                                    },
                                },
                            ],
                        },
                    },
                ],
            });

            return;
        }

        if (lastMessage.role === 'tool') {
            const userMessages = messages.filter(
                (message) => message?.role === 'user',
            );
            const latestUserMessage = userMessages.at(-1);
            const isApprovalJourney =
                typeof latestUserMessage?.content === 'string' &&
                /aprova(?:r|ção)/iu.test(latestUserMessage.content);
            const serviceName = isApprovalJourney
                ? (requestedServiceName(latestUserMessage.content) ??
                  approvedServiceName)
                : rejectedServiceName;

            json(response, 200, {
                id: 'e2e-fake-completion-final',
                object: 'chat.completion',
                choices: [
                    {
                        index: 0,
                        finish_reason: 'stop',
                        message: {
                            role: 'assistant',
                            content: isApprovalJourney
                                ? `Preparei uma proposta para o serviço ${serviceName}. Revise e aprove para concluir o cadastro.`
                                : `Preparei uma proposta para o serviço ${serviceName}. Revise e rejeite para manter o catálogo intacto.`,
                        },
                    },
                ],
            });

            return;
        }

        json(response, 400, { error: 'unsupported_message_role' });
    } catch (error) {
        json(response, 400, {
            error: error instanceof Error ? error.message : 'invalid_request',
        });
    }
});

server.on('error', (error) => {
    console.error(error instanceof Error ? error.message : error);
    process.exitCode = 1;
});

server.listen(port, host, () => {
    console.log(`Caldas E2E Groq fake listening on http://${host}:${port}`);
});

const shutdown = () => {
    server.close(() => process.exit(0));
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
