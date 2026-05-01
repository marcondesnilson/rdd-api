# AGENTS Bootstrap

Arquivo local enxuto. Padroes detalhados ficam nas skills.

## Skills a aplicar

1. `global-doc-agent` sempre
2. `laravel-doc-agent` para alteracoes backend Laravel

## Prioridade de contexto

1. `./documentation/index.md`
2. `/documentation/index.md` global, se existir
3. Estrutura real do projeto

## Regras locais

- Trabalhar apenas nesta pasta quando o escopo for a API.
- Backend-only: nao introduzir frontend, assets ou rotas web de produto sem necessidade explicita.
- Quando criar endpoints, seguir os contratos planejados no frontend em `../republica-do-direito/documentation/api-readiness.md`.
- Atualizar `./documentation` e `./documentation/index.md` apos alteracao funcional.
- Executar apenas validacoes deste projeto.

