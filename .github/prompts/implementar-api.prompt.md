---
description: "Implementa integração completa com API externa — service, tipos, error handling, retry (Magento 2)"
agent: "Awa"
tools:
  - codebase
  - edit
  - execute
  - changes
  - fetch
  - problems
---
Implemente uma integração REAL e COMPLETA com a API especificada no Magento 2.
## Checklist obrigatório:

1. **Pesquise a API** — Use #fetch para ler a documentação se tiver URL
2. **Crie a Service Interface** — Em `Api/` com métodos tipados
   - `\Magento\Framework\HTTP\Client\Curl` ou `GuzzleHttp\Client` via DI
   - Configurações via `system.xml` (URL, API key, timeout)
   - Métodos tipados para cada endpoint
   - Retry com exponential backoff (3 tentativas)
   - Tratamento de TODOS os status HTTP relevantes
   - Logging via `Psr\Log\LoggerInterface`
4. **Crie configurações admin** — `etc/adminhtml/system.xml` para credenciais
5. **Crie Helper/Config** — Para ler configurações do admin
6. **Valide** — `php -l`, verifique `di.xml`, limpe cache

## Regras ABSOLUTAS:
- ❌ ZERO código mock ou placeholder
- ❌ ZERO ObjectManager direto (use DI)
- ❌ ZERO secrets hardcoded (use system.xml + encrypted config)
- ✅ `declare(strict_types=1)` em todos os arquivos
- ✅ Error handling real com `try/catch` e Logger
- ✅ Type hints em todos os parâmetros e retornos
- ✅ Rate limiting quando a API exigir
