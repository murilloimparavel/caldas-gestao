<?php

namespace App\Support\Telemetry;

final class IncidentClassifier
{
    /** @return array<string, mixed> */
    public static function classify(string $kind, string $category, string $code, ?string $route, ?int $httpStatus = null, ?string $source = null, bool $hasFrames = false): array
    {
        $module = self::module($route);
        $base = ['title' => 'Incidente capturado', 'classification' => 'unclassified', 'module' => $module, 'severity' => 'medium', 'confidence' => 50, 'impact_summary' => 'Sinal aguardando avaliação técnica.', 'priority' => 30, 'auto_diagnosis_eligible' => false, 'diagnosis_threshold' => 3, 'http_status' => $httpStatus, 'detection_source' => $source ?? $kind];
        if ($kind === 'browser' && $category === 'inertia_http_exception' && ctype_digit($code)) {
            $httpStatus = (int) $code;
            $base['http_status'] = $httpStatus;
        }
        if ($httpStatus === 404) {
            return array_replace($base, ['title' => 'Página não encontrada', 'classification' => 'navigation', 'severity' => 'low', 'confidence' => 95, 'impact_summary' => 'Resposta 404 observada. A rota registrada indica a origem do evento, não o destino que falhou.', 'priority' => 10]);
        }
        if ($httpStatus !== null && $httpStatus >= 400 && $httpStatus < 500) {
            return array_replace($base, ['title' => 'Resposta de acesso ou validação', 'classification' => 'expected_request', 'severity' => 'low', 'confidence' => 90, 'impact_summary' => 'Resposta de cliente ou proteção de acesso; não indica por si só defeito de código.', 'priority' => 5]);
        }
        if ($source !== null) {
            $signals = self::signals();
            $signal = $signals[$source][$code] ?? null;

            return $signal === null ? $base : array_replace($base, $signal);
        }
        if ($kind === 'server' || ($httpStatus !== null && $httpStatus >= 500)) {
            $critical = in_array($module, ['booking', 'calendar', 'finance', 'authentication'], true);

            return array_replace($base, ['title' => 'Falha de aplicação no servidor', 'classification' => 'backend', 'severity' => $critical ? 'critical' : 'high', 'confidence' => 90, 'impact_summary' => 'Uma operação pode não ter sido concluída. A análise deve confirmar o impacto antes de propor alterações.', 'priority' => $critical ? 100 : 80, 'auto_diagnosis_eligible' => $module !== 'telemetry', 'diagnosis_threshold' => $kind === 'browser' ? 3 : ($critical ? 1 : 2)]);
        }
        if (in_array($category, ['javascript_error', 'unhandled_rejection'], true)) {
            return array_replace($base, ['title' => 'Falha de JavaScript', 'classification' => 'frontend', 'severity' => 'high', 'confidence' => $hasFrames ? 85 : 45, 'impact_summary' => 'Uma interação no navegador pode ter sido interrompida.', 'priority' => 70, 'auto_diagnosis_eligible' => $hasFrames, 'diagnosis_threshold' => 3]);
        }
        if ($category === 'network_error') {
            return array_replace($base, ['title' => 'Falha de conectividade', 'classification' => 'connectivity', 'confidence' => 70, 'impact_summary' => 'Uma requisição não foi concluída; conectividade deve ser verificada antes de atribuir a causa ao código.', 'priority' => 40]);
        }

        return $base;
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public static function signals(): array
    {
        return [
            'operational' => [
                'appointment_creation_failed' => ['title' => 'Falha na criação de agendamento', 'classification' => 'operation', 'module' => 'booking', 'severity' => 'critical', 'priority' => 100, 'confidence' => 95, 'impact_summary' => 'Uma tentativa de agendamento não foi concluída.', 'auto_diagnosis_eligible' => true, 'diagnosis_threshold' => 1],
                'sale_finalization_failed' => ['title' => 'Falha na finalização de venda', 'classification' => 'operation', 'module' => 'finance', 'severity' => 'critical', 'priority' => 100, 'confidence' => 95, 'impact_summary' => 'Uma operação financeira não foi concluída.', 'auto_diagnosis_eligible' => true, 'diagnosis_threshold' => 2],
                'runner_unavailable' => ['title' => 'Executor de diagnósticos indisponível', 'classification' => 'operation', 'module' => 'telemetry', 'severity' => 'medium', 'priority' => 50, 'confidence' => 95, 'impact_summary' => 'A preparação automática de diagnósticos está indisponível.', 'auto_diagnosis_eligible' => false],
            ],
            'integrity' => [
                'orphaned_appointment_item' => ['title' => 'Anomalia de vínculo de agendamento', 'classification' => 'data_integrity', 'module' => 'booking', 'severity' => 'high', 'priority' => 90, 'confidence' => 95, 'impact_summary' => 'Um vínculo de dados requer verificação; nenhuma correção de dados é automática.', 'auto_diagnosis_eligible' => true, 'diagnosis_threshold' => 2],
                'unbalanced_closing' => ['title' => 'Divergência no fechamento', 'classification' => 'data_integrity', 'module' => 'finance', 'severity' => 'critical', 'priority' => 100, 'confidence' => 95, 'impact_summary' => 'O fechamento requer conferência de consistência; valores e clientes não são coletados.', 'auto_diagnosis_eligible' => true, 'diagnosis_threshold' => 2],
            ],
            'performance' => [
                'slow_route' => ['title' => 'Rota com tempo elevado', 'classification' => 'performance', 'severity' => 'medium', 'priority' => 35, 'confidence' => 75, 'impact_summary' => 'O tempo de resposta requer avaliação de desempenho.', 'auto_diagnosis_eligible' => false],
                'slow_query' => ['title' => 'Consulta com tempo elevado', 'classification' => 'performance', 'severity' => 'medium', 'priority' => 35, 'confidence' => 75, 'impact_summary' => 'Uma consulta requer avaliação de desempenho; SQL e parâmetros não são coletados.', 'auto_diagnosis_eligible' => false],
            ],
            'internal_navigation' => [
                'broken_internal_link' => ['title' => 'Falha confirmada de navegação interna', 'classification' => 'internal_navigation', 'severity' => 'medium', 'priority' => 60, 'confidence' => 90, 'impact_summary' => 'Um detector confiável confirmou uma falha de navegação interna.', 'auto_diagnosis_eligible' => true, 'diagnosis_threshold' => 3],
            ],
        ];
    }

    private static function module(?string $route): string
    {
        if (str_starts_with($route ?? '', 'admin.incidents.')) {
            return 'telemetry';
        }
        $prefix = explode('.', $route ?? '')[0];

        return match ($prefix) {
            'public_booking', 'online_booking' => 'booking',
            'calendar', 'appointments', 'availability_rules' => 'calendar',
            'sales', 'billing', 'cash', 'closings', 'payments' => 'finance',
            'customers' => 'customers',
            'login', 'register', 'password', 'verification', 'profile', 'two-factor', 'passkeys' => 'authentication',
            'telemetry' => 'telemetry',
            default => 'platform',
        };
    }
}
