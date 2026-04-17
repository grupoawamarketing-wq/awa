# Smoke Checklist Operacional

## Objetivo
Fornecer um checklist rapido de validacao apos alteracoes em areas criticas do projeto.

## Smoke Geral
- [ ] homepage abre sem erro visivel
- [ ] busca funciona
- [ ] pdp carrega sem erro de JS
- [ ] minicart abre corretamente
- [ ] login funciona
- [ ] logs nao mostram erro novo relevante

## B2B
- [ ] login B2B abre e envia formulario
- [ ] cadastro B2B carrega etapas e validacoes
- [ ] pagina de claim/forgot abre corretamente
- [ ] regras visuais nao quebraram shell de autenticacao

## Checkout
- [ ] carrinho renderiza
- [ ] resumo de pedido carrega
- [ ] metodo de envio seleciona corretamente
- [ ] metodo de pagamento aparece
- [ ] mensagens de erro sao exibidas corretamente

## ERP E Integracoes
- [ ] endpoints internos respondem sem erro
- [ ] sincronizacao critica nao apresenta excecao imediata
- [ ] filas e logs nao mostram falha nova

## SEO / Metadata
- [ ] title e meta description corretos
- [ ] canonical presente quando esperado
- [ ] open graph renderiza sem duplicacao

## Comandos Rapidos
```bash
tail -20 var/log/system.log
tail -20 var/log/exception.log
composer quality:all
```
