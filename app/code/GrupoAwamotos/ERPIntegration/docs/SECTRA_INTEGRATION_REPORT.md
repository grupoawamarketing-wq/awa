# Relatório: Integração Sectra - Erro "Cliente não foi encontrado"

## Data: 2026-02-25

---

## 1. Problema

Ao importar pedidos no Sectra (Comercial > Ferramentas > Integração AWA > Importar Pedidos), o erro:

```
Cliente não foi encontrado - Pedido: 200008, Cliente no B2B: (2541) MAM, Valor: 37370,29!
```

## 2. Causa Raiz

O Sectra valida clientes contra a tabela `GR_INTEGRACAOVALIDADOR` antes de importar pedidos web.

| Item | Valor |
|------|-------|
| Tabela de validação | `GR_INTEGRACAOVALIDADOR` |
| Origem de integração | `OpenCardB2B - Cadastro de Cliente` |
| GUID | `7D4C6FBD-62CF-427F-A0ED-3C06602F05D7` |
| Total registrados | 1.306 clientes |
| Total Magento B2B | 8.578 clientes |
| NÃO registrados | ~7.354 clientes |

**Cliente 2541 (MAM DISTRIBUIDORA)** existe no ERP (`FN_FORNECEDORES`) mas NÃO está registrado no `GR_INTEGRACAOVALIDADOR`. Quando o Sectra tenta importar o pedido 200008, a validação falha.

### Por que não está registrado?

O sync "OpenCardB2B - Cadastro de Cliente" foi implementado pelo **lado Sectra** (middleware desktop). Ele sincronizava clientes que tinham conta na plataforma OpenCart B2B antiga. Como o cliente 2541 nunca teve conta no OpenCart, nunca foi registrado.

O sync ainda está ativo (último registro: 2026-02-19) mas só processa clientes já conhecidos pelo OpenCard, não os novos do Magento.

## 3. Evidências

### Pedidos importados com sucesso (cliente 699 - registrado)
```
VE_PEDIDO: PEDIDOWEB=200007, CLIENTE=699, STATUS=W
GR_INTEGRACAOVALIDADOR: CHAVE=699, VALIDADOR=329F655A..., ORIGEM=Cadastro de Cliente
```

### Pedido com erro (cliente 2541 - NÃO registrado)
```
GR_INTEGRACAOVALIDADOR: CHAVE=2541 → NÃO ENCONTRADO
```

### Análise do OpenCart
O repositório OpenCart (awamotossiteground.git) **NÃO contém código de integração com SQL Server**. Apenas expõe APIs REST com JWT auth. O Sectra consumia `GET /api/order` e fazia todas as escritas no SQL Server pelo seu próprio código.

## 4. Soluções Implementadas

### 4.1 API de Clientes B2B (NOVO)

Endpoints REST para o Sectra consultar clientes Magento:

| Endpoint | Descrição |
|----------|-----------|
| `GET /V1/erp/customers/b2b` | Lista todos os clientes B2B com ERP codes |
| `GET /V1/erp/customers/b2b/unregistered` | Lista clientes NÃO registrados no Sectra |
| `GET /V1/erp/customers/b2b/:erpCode` | Detalhes de um cliente com dados ERP |

**Autenticação**: Bearer token `ljlpaset15...` (integração SECTRA ERP)

**Exemplo**: `GET /V1/erp/customers/b2b/2541`
```json
{
    "magento_id": 819,
    "erp_code": 2541,
    "name": "MAM DISTRIBUIDORA DE MOTO PECAS LTDA",
    "registered_in_b2b": false,
    "erp_data": {
        "razao": "MAM DISTRIBUIDORA DE MOTO PECAS LTDA",
        "cnpj": "10.860.222/0001-38",
        "ie": "336.883.908.114",
        "vendedor": 111,
        "cond_pagto": 24,
        "fator_preco": 25,
        "transportadora": 728
    }
}
```

### 4.2 Serviço B2BClientRegistration

Classe: `GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration`

- `isClientRegistered($erpCode)` — Verifica se cliente está no validador
- `registerClient($erpCode)` — Auto-registra (se write credentials configuradas)
- `generateRegistrationSQL($erpCodes)` — Gera SQL para execução manual

### 4.3 CLI Command

```bash
bin/magento erp:client:register --check 2541       # Verifica status
bin/magento erp:client:register --generate-sql 2541 # Gera SQL
bin/magento erp:client:register --pending           # Clientes com pedidos pendentes
```

### 4.4 Auto-registro na API de Pedidos

O `OrderPullManagement::buildOrderPayload()` agora:
1. Verifica se o cliente está registrado no validador
2. Tenta auto-registrar se credenciais de escrita disponíveis
3. Retorna `customer.registered_in_b2b: true/false` na resposta

## 5. Ações Necessárias (Time Sectra/DBA)

### 5.1 Fix Imediato (Cliente 2541)

Executar no SQL Server Management Studio com usuário de escrita:

```sql
BEGIN TRANSACTION;

INSERT INTO GR_INTEGRACAOVALIDADOR (INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)
VALUES ('7D4C6FBD-62CF-427F-A0ED-3C06602F05D7', '2541', '28A417A4FFC36C8D8AD0FE4B2010867E', '2541', GETDATE());

INSERT INTO GR_INTEGRACAOVALIDADOR (INTEGRACAOORIGEM, CHAVE, VALIDADOR, CHAVEEXTERNA, DTSINCRONIZACAO)
VALUES ('FEB11981-5319-49EB-9F1E-4BA02BD22B90', '2541;1', '0C23D6E429075F273DA6D9F2128263F7', '10426', GETDATE());

COMMIT;
```

### 5.2 Fix Completo (Todos os 7.354 clientes)

Arquivo SQL completo: `/tmp/sectra_register_all_clients.sql`

### 5.3 Solução Permanente (escolher uma)

**Opção A**: Fornecer credenciais de escrita para o Magento
- Criar usuário SQL Server com INSERT no `GR_INTEGRACAOVALIDADOR`
- Configurar em Magento Admin > Stores > Configuration > ERP Integration > Write Connection
- Auto-registro será feito automaticamente via API

**Opção B**: Reconfigurar sync do Sectra
- Alterar "OpenCardB2B - Cadastro de Cliente" para consumir `GET /V1/erp/customers/b2b`
- O endpoint retorna todos os clientes Magento B2B com dados ERP completos

## 6. Estrutura do GR_INTEGRACAOVALIDADOR

| Campo | Tipo | Descrição |
|-------|------|-----------|
| INTEGRACAOORIGEM | varchar(GUID) | Tipo de integração |
| CHAVE | varchar | Chave primária (CODIGO do cliente, ou CODIGO;FILIAL para endereço) |
| VALIDADOR | varchar(32) | Hash MD5 dos dados (para detectar mudanças) |
| CHAVEEXTERNA | varchar | ID externo (Magento customer_id ou sequence) |
| DTSINCRONIZACAO | datetime | Data da última sincronização |

### GUIDs de Integração

| GUID | Descrição | Status Sync |
|------|-----------|-------------|
| 7D4C6FBD-...D7 | Cadastro de Cliente | Parado em 2024-11-01 |
| FEB11981-...90 | Endereço de Cliente | Parado em 2024-11-01 |
| CC063BDC-...AE | Pedido | Ativo (2026-02-23) |
| 753ADB36-...A8 | Pré-Cadastro | Ativo |
| 72469087-...6C | Preço | Parado em 2024-11-01 |

## 7. Arquivos Criados/Modificados

### Novos
- `Api/CustomerPullInterface.php` — Interface da API de clientes
- `Model/Api/CustomerPullManagement.php` — Implementação
- `Model/B2BClientRegistration.php` — Serviço de registro
- `Console/Command/RegisterB2BClientCommand.php` — CLI command

### Modificados
- `etc/webapi.xml` — Rotas da API de clientes
- `etc/di.xml` — DI preferences e types
- `etc/adminhtml/system.xml` — Config de write connection
- `Helper/Data.php` — Métodos write connection
- `Model/Api/OrderPullManagement.php` — Auto-registro + flag registered_in_b2b
