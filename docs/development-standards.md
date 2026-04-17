# Guia De Padroes De Desenvolvimento

## Objetivo
Este guia consolida as melhores praticas adotadas pelo time para desenvolvimento, revisao, release e manutencao do software. O foco e reduzir ambiguidade, acelerar onboarding e melhorar previsibilidade de entrega.

## 1. Convencoes De Nomenclatura

### Regras Gerais
- prefira nomes que expressem intencao
- evite abreviacoes vagas como `tmp`, `cfg`, `misc`, `util2`
- use verbos para acoes e substantivos para entidades
- prefixe booleanos com `is`, `has`, `can` ou `should`

### Padroes
- classes, interfaces, view models e DTOs: `PascalCase`
- metodos, funcoes e variaveis: `camelCase`
- constantes: `SCREAMING_SNAKE_CASE`
- arquivos CSS, JS, templates e assets: `kebab-case`
- endpoints REST: substantivos no plural

### Exemplos
```txt
CustomerRepository
OpenGraphViewModel
registerForm.js
customer-account.css
MAX_RETRY_ATTEMPTS
isApproved
hasCreditLimit
```

## 2. Estrutura De Diretorios

### Backend Magento
```txt
app/code/Vendor/Modulo/
├── Api/
├── Api/Data/
├── Block/
├── Controller/
├── Cron/
├── Helper/
├── Model/
├── Observer/
├── Plugin/
├── etc/
│   ├── adminhtml/
│   └── frontend/
└── view/
    ├── adminhtml/
    └── frontend/
```

### Regras
- separe responsabilidade de dominio, integracao, interface e infraestrutura
- nao concentre logica em `Helper` sem necessidade
- prefira `ViewModel`, `Service Contracts`, `Repository` e classes focadas
- mantenha testes proximos do contexto que validam

## 3. Commits Semanticos

### Formato
```txt
tipo(escopo-opcional): resumo curto
```

### Tipos
- `feat`
- `fix`
- `refactor`
- `perf`
- `test`
- `docs`
- `build`
- `ci`
- `chore`
- `revert`

### Exemplos
```txt
feat(b2b): adiciona etapa de confirmacao no cadastro pj
fix(theme): corrige ordem de carregamento do css do blog
refactor(schemaorg): centraliza geracao de metadados
ci(workflows): adiciona validacoes minimas de governanca
```

### Boas Praticas
- um assunto principal por commit
- nao misture refactor e bugfix sem necessidade
- use corpo do commit para explicar o motivo

## 4. Linting E Formatacao Automatica

### Fontes Oficiais Do Projeto
- `.editorconfig`
- `phpcs.xml`
- `.php-cs-fixer.dist.php`
- `package.json`

### Diretrizes
- lint deve capturar erro de padrao e anti-pattern
- formatter deve resolver estilo automaticamente
- review nao deve discutir espacos, ponto e virgula ou ordem de imports
- toda validacao automatica deve rodar localmente e em CI

### Exemplo De Fluxo
```bash
phpcs --standard=phpcs.xml app/code/GrupoAwamotos
php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff
```

## 5. Documentacao De Codigo

### Quando Documentar
- contratos publicos
- integracoes externas
- comportamento nao obvio
- regras de negocio criticas
- efeitos colaterais, retries e limites operacionais

### Boas Praticas
- documente o por que, nao apenas o que
- mantenha docblocks curtos e objetivos
- atualize documentacao de API e operacao junto do codigo

### Exemplo De DocBlock
```php
/**
 * Sincroniza estoque com a origem ERP respeitando janela de tolerancia.
 *
 * @param string $sku
 * @return void
 * @throws LocalizedException
 */
```

## 6. Versionamento Semantico

### Regra
- `MAJOR`: quebra de compatibilidade
- `MINOR`: nova funcionalidade compativel
- `PATCH`: correcao compativel

### Exemplos
- remover campo publico de API: `MAJOR`
- adicionar endpoint opcional: `MINOR`
- corrigir validacao sem alterar contrato: `PATCH`

### Recomendacao
- descreva impacto de versao em cada PR
- mantenha changelog por release

## 7. Testes

### Testes Unitarios
- validam regras de negocio e comportamento isolado
- devem ser rapidos, deterministas e legiveis
- use nomes de teste descritivos

### Testes De Integracao
- validam modulo com banco, fila, cache ou API externa controlada
- devem cobrir autenticacao, pedidos, estoque e fluxos financeiros

### Regras
- fluxos criticos precisam de cobertura proporcional ao risco
- nao crie testes ruidosos que apenas repetem implementacao
- todo bug recorrente deve ganhar teste de regressao quando fizer sentido

### Estrutura Sugerida
```txt
tests/
├── unit/
├── integration/
└── e2e/
```

## 8. Integracao Continua

### Minimo Obrigatorio
- validacao de sintaxe
- validacao de configuracoes centrais
- lint de codigo customizado
- checagem de arquivos obrigatorios de governanca

### Evolucao Recomendada
- suite de testes unitarios
- suite de integracao
- verificacao de cobertura
- scans de seguranca e dependencias

## 9. Templates De PR E Issues

### PR Deve Informar
- objetivo
- tipo de mudanca
- impacto
- como testar
- risco
- checklist de qualidade

### Issue De Bug Deve Conter
- comportamento atual
- comportamento esperado
- passos para reproduzir
- evidencias
- ambiente

### Issue De Melhoria Deve Conter
- contexto
- proposta
- criterios de aceite
- dependencias

## 10. Padrao De API RESTful

### Recursos
- use substantivos no plural
- prefira rotas previsiveis e sem verbos

### Exemplos
```txt
GET    /api/v1/customers
GET    /api/v1/customers/{id}
POST   /api/v1/customers
PATCH  /api/v1/customers/{id}
DELETE /api/v1/customers/{id}
```

### Respostas
- `200 OK`: consulta ou atualizacao bem-sucedida
- `201 Created`: criacao
- `204 No Content`: remocao sem payload
- `400 Bad Request`: payload invalido
- `401 Unauthorized`: autenticacao ausente ou invalida
- `403 Forbidden`: sem permissao
- `404 Not Found`: recurso inexistente
- `409 Conflict`: conflito de estado
- `422 Unprocessable Entity`: regra de negocio violada

### Padrao De Erro
```json
{
  "error": {
    "code": "validation_error",
    "message": "Payload invalido",
    "details": [
      {
        "field": "email",
        "message": "Formato invalido"
      }
    ]
  },
  "meta": {
    "request_id": "req_123"
  }
}
```

## 11. Checklist De Seguranca

### Validacao De Entrada
- [ ] campos obrigatorios presentes
- [ ] tipos corretos
- [ ] tamanhos minimos e maximos
- [ ] formato e dominio validos
- [ ] duplicidade tratada
- [ ] regras de negocio aplicadas

### Aplicacao
- [ ] autenticacao e autorizacao explicitas
- [ ] protecao contra XSS, CSRF e injection
- [ ] logs sem dados sensiveis
- [ ] segredos fora do codigo
- [ ] rate limiting em pontos criticos
- [ ] rastreabilidade com `request_id` ou `correlation_id`
- [ ] mensagens de erro sem vazamento interno

## 12. Processo De Code Review

### Objetivo
Code review deve priorizar risco, comportamento, clareza e manutencao. Estilo automatico pertence ao lint e ao formatter.

### Checklist
- [ ] a mudanca resolve o problema proposto
- [ ] o fluxo principal esta correto
- [ ] casos de erro foram considerados
- [ ] nomes e responsabilidades estao claros
- [ ] a arquitetura Magento foi respeitada
- [ ] nao ha acoplamento desnecessario
- [ ] seguranca foi considerada
- [ ] testes relevantes existem ou ha justificativa
- [ ] documentacao foi atualizada quando aplicavel

## 13. Templates Reutilizaveis

### Estrutura De PR Pequeno
```md
## Objetivo
Descreva o problema e a solucao.

## Impacto
- funcional:
- tecnico:
- operacional:

## Como testar
1. passo 1
2. passo 2
3. resultado esperado
```

### Estrutura De Checklist Tecnico
```md
- [ ] lint ok
- [ ] format ok
- [ ] testes executados
- [ ] sem segredo hardcoded
- [ ] tratamento de erro revisado
- [ ] documentacao atualizada
```

## 14. Metricas De Sucesso

### Adocao
- percentual de commits no padrao semantico
- percentual de PRs com template completo
- percentual de merges com CI verde na primeira execucao

### Qualidade
- taxa de bugs regressivos por release
- tempo medio ate primeira revisao
- cobertura de fluxos criticos
- numero de falhas de seguranca abertas por severidade

### Metas Iniciais Recomendadas
- `>= 95%` dos commits em `Conventional Commits`
- `>= 90%` dos PRs com checklist completo
- `< 24h` para primeira revisao
- reducao de regressao a cada ciclo trimestral

## 15. Definicao De Pronto
Uma mudanca esta pronta para merge quando:
- atende ao objetivo proposto
- passou por validacoes minimas
- tem risco conhecido descrito
- possui evidencias proporcionais ao impacto
- esta aderente a este guia e ao `CONTRIBUTING.md`
