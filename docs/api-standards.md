# Padroes De API E Web APIs Magento

## Objetivo
Padronizar APIs HTTP e Web APIs Magento para manter consistencia de contrato, seguranca, versionamento e facilidade de manutencao.

## Escopo
Aplica-se a:
- `etc/webapi.xml`
- services em `Api/`
- DTOs em `Api/Data/`
- controllers customizados que exponham endpoints HTTP
- integracoes internas e externas com retorno JSON

## Principios
- contratos claros e estaveis
- validacao no backend, nao apenas no cliente
- erros previsiveis e padronizados
- versionamento explicito
- rastreabilidade de requisicao

## Recursos E Rotas
- use substantivos no plural para colecoes
- evite verbos em rotas quando o metodo HTTP ja expressa a acao
- prefira caminhos estaveis e sem ambiguidade

### Exemplos
```txt
GET    /rest/V1/customers
GET    /rest/V1/customers/:id
POST   /rest/V1/customers
PUT    /rest/V1/customers/:id
PATCH  /rest/V1/customers/:id
DELETE /rest/V1/customers/:id
```

## Verbos HTTP
- `GET`: leitura sem efeito colateral
- `POST`: criacao ou comando controlado
- `PUT`: substituicao completa
- `PATCH`: atualizacao parcial
- `DELETE`: remocao logica ou fisica conforme contrato

## Padrao De Payload
- escolha um unico padrao de chaves por API e nao misture `camelCase` com `snake_case`
- prefira nomes explicitos e semanticamente estaveis
- nunca exponha campos internos sem necessidade

## Respostas

### Sucesso
```json
{
  "data": {
    "id": "123",
    "status": "active"
  },
  "meta": {
    "request_id": "req_001"
  }
}
```

### Erro
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
    "request_id": "req_001"
  }
}
```

## Status Codes
- `200`: leitura ou atualizacao bem-sucedida
- `201`: recurso criado
- `204`: operacao sem payload
- `400`: payload malformado
- `401`: autenticacao ausente ou invalida
- `403`: autorizado insuficiente
- `404`: recurso nao encontrado
- `409`: conflito de estado
- `422`: regra de negocio violada
- `500`: erro interno inesperado

## Versionamento
- preserve compatibilidade sempre que possivel
- mudancas incompativeis exigem nova versao de contrato
- toda quebra deve ser registrada em release e PR

### Regras
- adicionar campo opcional: compativel
- alterar tipo, semantica ou obrigatoriedade de campo: potencial quebra
- remover campo publico: quebra

## Validacao
- valide tipos, obrigatoriedade, faixas e formato
- normalize entrada quando necessario
- retorne erro claro por campo quando fizer sentido
- nao silencie falhas de integracao

## Seguranca
- valide autorizacao por recurso e acao
- aplique rate limiting quando o endpoint for sensivel
- nao retorne stack trace ou detalhes internos
- nao logue segredos, tokens ou dados sensiveis sem mascaramento

## Magento Web API
- prefira expor contratos via `Api/` e `etc/webapi.xml`
- mantenha interfaces estaveis e DTOs tipados
- evite logica de negocio em controller quando a exposicao for REST
- documente permissao e ACL quando houver rota autenticada

## Checklist De Review Para API
- [ ] rota segue padrao previsivel
- [ ] metodo HTTP adequado
- [ ] contrato documentado
- [ ] validacao de entrada robusta
- [ ] resposta de erro padronizada
- [ ] autorizacao revisada
- [ ] impacto de versao avaliado
- [ ] testes cobrindo sucesso e falha
