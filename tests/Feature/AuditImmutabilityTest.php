<?php

use App\Logging\MaskSensitiveDataProcessor;
use App\Models\AuditEvent;
use Monolog\Level;
use Monolog\LogRecord;

it('prevents direct modification (updating) of audit events', function () {
    $auditEvent = AuditEvent::factory()->create([
        'action' => 'user.created',
    ]);

    expect(fn () => $auditEvent->update(['action' => 'user.updated']))
        ->toThrow(LogicException::class, 'Audit events are append-only.');

    expect($auditEvent->fresh()->action)->toBe('user.created');
});

it('prevents direct deletion of audit events', function () {
    $auditEvent = AuditEvent::factory()->create([
        'action' => 'user.created',
    ]);

    expect(fn () => $auditEvent->delete())
        ->toThrow(LogicException::class, 'Audit events are append-only.');

    expect(AuditEvent::query()->where('id', $auditEvent->id)->exists())->toBeTrue();
});

it('masks sensitive PII fields in log context, extra, and message', function () {
    $processor = new MaskSensitiveDataProcessor;

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'testing',
        level: Level::Info,
        message: 'User registered with cpf=12345678900 and password=secretpass',
        context: [
            'cpf' => '123.456.789-00',
            'phone' => '(11) 99999-8888',
            'password' => 'supersecret',
            'credit_card' => '4111222233334444',
            'safe_field' => 'visible_data',
            'nested' => [
                'cpf' => '98765432100',
                'user_id' => 123,
            ],
        ],
        extra: [
            'token' => 'bearer-token-xyz',
        ]
    );

    $processed = $processor($record);

    expect($processed->context['cpf'])->toBe('********')
        ->and($processed->context['phone'])->toBe('********')
        ->and($processed->context['password'])->toBe('********')
        ->and($processed->context['credit_card'])->toBe('********')
        ->and($processed->context['safe_field'])->toBe('visible_data')
        ->and($processed->context['nested']['cpf'])->toBe('********')
        ->and($processed->context['nested']['user_id'])->toBe(123)
        ->and($processed->extra['token'])->toBe('********')
        ->and($processed->message)->toContain('cpf=********')
        ->and($processed->message)->toContain('password=********');
});
