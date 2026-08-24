# Máquinas de estado propostas

Estas máquinas são propostas independentes; não representam implementação interna do alvo.

## Obrigação financeira

```text
draft → scheduled → available → partially_settled → settled
                   ↘ overdue
available/overdue → blocked → available
qualquer não final → cancelled
settled → adjusted/reversed
```

## Caixa

```text
closed → opening → open → counting → closed
                            ↘ disputed → closed
closed → reopened (papel elevado + motivo)
```

## Comissão

```text
projected → accrued → approved → payable → paid
                 ↘ disputed
paid → adjusted
```

## Documento fiscal

```text
draft → queued → processing → authorized
                    ↘ rejected → corrected → queued
authorized → cancel_requested → cancelled
```

Estados operacionais diferentes jamais devem ser representados por um único booleano `paid`, `closed` ou `issued`.
