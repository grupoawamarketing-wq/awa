# Estrategia De Testes

## Objetivo
Definir uma abordagem pratica e progressiva para testes unitarios, integracao e end-to-end no projeto.

## Principios
- teste comportamento, nao detalhes irrelevantes de implementacao
- cobertura deve ser proporcional ao risco
- prefira testes deterministas, pequenos e legiveis
- bugs recorrentes devem gerar teste de regressao quando fizer sentido

## Camadas

### Testes Unitarios
- validam regras de negocio isoladas
- devem ser rapidos e independentes de infraestrutura
- ideais para services, validadores, mapeadores e regras criticas

### Testes De Integracao
- validam integracao entre modulo, banco, fila, cache ou APIs controladas
- sao prioritarios para autenticação, checkout, pedidos, ERP e estoque

### Testes End To End
- validam jornada real do usuario
- devem cobrir fluxos criticos de conversao e operacao
- usar com parcimonia por custo e manutencao

## Estrutura Recomendada
```txt
tests/
├── unit/
├── integration/
└── e2e/
```

## Prioridades Por Area

### B2B
- login
- cadastro
- aprovacao
- cotacao
- regras de credito

### ERP / Integracoes
- sincronizacao de estoque
- sincronizacao de preco
- persistencia de pedidos
- tratamento de falha e retry

### Tema / Frontend
- paginas criticas: home, plp, pdp, carrinho, checkout, login
- regressao visual apenas onde traz valor
- scripts customizados com risco de UX ou conversao

## Convencoes
- nome do teste deve descrever comportamento esperado
- cada teste deve ter um motivo claro para falhar
- se o setup estiver grande demais, o design pode precisar de refactor

### Exemplos De Nome
```txt
deve_criar_conta_b2b_quando_payload_for_valido
deve_retornar_erro_quando_cnpj_estiver_invalido
shouldRenderLoginFormWhenCustomerIsLoggedOut
```

## Quando Adicionar Teste
- sempre que corrigir bug relevante
- quando o fluxo tiver alto impacto em negocio
- quando a regra de negocio tiver multiplas variacoes
- quando uma integracao puder falhar silenciosamente

## Quando Nao Exagerar
- nao replique a implementacao linha a linha
- nao adicione teste ruidoso para markup estatico sem risco real
- nao use e2e para cobrir algo que um teste unitario cobre melhor

## Checklists

### Unitario
- [ ] cobre fluxo principal
- [ ] cobre erro relevante
- [ ] sem dependencia externa desnecessaria
- [ ] facil de entender

### Integracao
- [ ] valida contrato entre camadas
- [ ] cobre persistencia ou integracao real/controlada
- [ ] observa comportamento de erro
- [ ] gera evidencias uteis

### E2E
- [ ] cobre jornada critica
- [ ] evita excesso de flakiness
- [ ] usa seletores estaveis
- [ ] tem escopo pequeno e objetivo

## Metricas
- cobertura em modulos criticos
- taxa de bugs regressivos por release
- tempo medio para detectar falha
- percentual de suites passando na primeira execucao

## Execucao Recomendada
- local: rode o menor conjunto relevante para a mudanca
- PR: ao menos sintaxe, governanca e suites criticas
- release: fluxos de negocio prioritarios e smoke tests operacionais
