# Baseline de performance web

Este documento registra a linha de base e os critérios de validação da
experiência web do Caldas Gestão. Os números são medições de navegação, não uma
garantia para todas as redes, aparelhos ou rotas autenticadas.

## Medição de produção (baseline observado)

Rota pública testada: `https://gestao.romawear.com.br/login`.

| Ambiente | Resposta inicial | Primeiro paint | DOM/load | Requisições | Erros JS/rede |
| --- | ---: | ---: | ---: | ---: | --- |
| Mobile, 390×844 | 1.149 ms | 1.912 ms | 2.157 ms | 28 | nenhum |
| Desktop, 1.440×900 | 869 ms | 1.352 ms | 1.866 ms | 28 | nenhum |

Antes da otimização, a mesma rota carregava o entrypoint principal com cerca de
241 KB e o shell autenticado era incluído estaticamente. A build otimizada
reduziu o entrypoint para cerca de 89 KB e separou o layout autenticado em um
chunk próprio de cerca de 69 KB.

## Melhorias implementadas

- layouts de aplicação, autenticação e configurações carregados sob demanda;
- fallback visual inline no HTML inicial, com suporte aos temas claro e escuro;
- remoção do fallback somente depois que a aplicação React monta;
- fallback preservado quando o JavaScript não inicializa, evitando uma tela
  vazia indistinguível de uma tela preta.

## Critérios de aceite

- nenhum erro de console ou requisição crítica falha em uma navegação limpa;
- o primeiro conteúdo útil aparece em até 2,5 s em mobile de referência;
- o entrypoint inicial permanece abaixo de 100 KB sem gzip;
- rotas públicas não carregam o layout autenticado antes de precisar dele;
- a navegação autenticada mantém o mesmo comportamento funcional;
- a versão publicada é confirmada pelo manifest e pelo HTML efetivamente
  servidos no domínio Premium.

## Estado de publicação

O código otimizado foi publicado na `main` e o workflow do cPanel concluiu, mas
`gestao.romawear.com.br` aponta para a VPS/Coolify, não para o cPanel. A produção
Premium ainda servia o manifest anterior na última medição. A validação final
depende de publicar o commit no recurso Coolify que atende esse domínio e
repetir as medições acima, incluindo as rotas autenticadas e o link público de
agendamento.
