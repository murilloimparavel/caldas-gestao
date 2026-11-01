<?php

return [
    'enabled' => env('TELEMETRY_ENABLED', env('APP_ENV') === 'production'),
    'release' => env('APP_RELEASE', env('APP_VERSION', 'unknown')),
    'probe' => env('TELEMETRY_PROBE', false),
    'retention_days' => 30,
    'max_records' => 1000,
    'browser_threshold' => 3,
    'lease_minutes' => 60,
    'max_attempts' => 2,
    'per_ip_per_minute' => 10,
    'global_per_minute' => 100,
    'server_per_minute' => 120,
    'slow_route_ms' => 3000,
    'slow_query_ms' => 1500,
    'performance_sample_rate' => 0.1,
    'classifications' => ['unclassified' => 'Não classificado', 'navigation' => 'Navegação', 'expected_request' => 'Acesso e validação', 'backend' => 'Backend', 'frontend' => 'Frontend', 'connectivity' => 'Conectividade', 'internal_navigation' => 'Navegação interna', 'operation' => 'Operação', 'data_integrity' => 'Integridade de dados', 'performance' => 'Desempenho'],
    'modules' => ['booking' => 'Agendamento', 'calendar' => 'Agenda', 'finance' => 'Financeiro', 'customers' => 'Clientes', 'authentication' => 'Autenticação', 'telemetry' => 'Telemetria', 'platform' => 'Plataforma'],
    'severities' => ['critical' => 'Crítica', 'high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'],
    'workflow_statuses' => ['new' => 'Novo', 'triaged' => 'Triado', 'in_analysis' => 'Em análise', 'proposal_ready' => 'Proposta pronta', 'in_validation' => 'Em validação', 'resolved' => 'Resolvido', 'ignored' => 'Ignorado'],
    'workflow_reasons' => ['triage_confirmed' => 'Triagem confirmada', 'investigation_started' => 'Investigação iniciada', 'proposal_recorded' => 'Proposta registrada', 'validation_started' => 'Validação iniciada', 'fix_verified' => 'Correção verificada', 'duplicate' => 'Duplicado', 'not_actionable' => 'Sem ação necessária', 'owner_assigned' => 'Responsável atualizado', 'reopened' => 'Reaberto'],
    'workflow_transitions' => ['new' => ['triaged', 'ignored'], 'triaged' => ['in_analysis', 'ignored'], 'in_analysis' => ['proposal_ready', 'triaged', 'ignored'], 'proposal_ready' => ['in_validation', 'in_analysis', 'ignored'], 'in_validation' => ['resolved', 'in_analysis', 'ignored'], 'resolved' => ['triaged'], 'ignored' => ['triaged']],
];
