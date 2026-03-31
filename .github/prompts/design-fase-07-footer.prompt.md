---
description: "AWA Design System — Fase 7: Rodapé Profissional"
agent: "agent"
tools:
  - codebase
  - changes

---

> Skill carregada automaticamente: `design-system` (bundles, tokens, BEM header, deploy).

Você é um especialista em UI/UX Magento 2. Implemente um rodapé profissional B2B
para AWA Motos. Não alterar nada acima da tag <footer>.

ESTRUTURA DO RODAPÉ (4 colunas desktop):
1. Coluna Marca: Logo + descrição empresa + redes sociais
2. Coluna Links: Links institucionais (Sobre, Contato, Blog)
3. Coluna Serviços: Minha Conta, Pedidos, Rastreamento, Atacado
4. Coluna Contato: Telefone, WhatsApp, E-mail, Horário

DESIGN:
- Background: var(--awa-dark, #333) (cinza escuro corporativo)
- Color: rgba(255,255,255,.85)
- Padding: 64px 0 32px (desktop), 40px 0 24px (mobile)
- Links: color rgba(255,255,255,.7), hover color #fff, hover text-decoration none
- Títulos de coluna: color #fff, font-size 12px, font-weight 700, text-transform uppercase, letter-spacing .06em, margin-bottom 16px
- Divisor: border-top 1px solid rgba(255,255,255,.12) antes do copyright

BARRA DE COPYRIGHT:
- Background: rgba(0,0,0,.2) (mais escuro que o footer)
- Padding: 16px 0
- Font-size: 12px, color rgba(255,255,255,.5)
- Layout: flex, space-between (texto + badges de pagamento)

BADGES DE PAGAMENTO:
- Visa, Mastercard, Pix, Boleto — SVG inline ou img
- Height: 24px, opacity .7

SELETORES:
- body .page-wrapper .page-footer (container principal)
- body .page-wrapper .footer.content
- body .page-wrapper .footer-links (se existir)

VERIFICAR: Estrutura atual do footer nos layouts XML do Rokanthemes antes de editar.
ARQUIVO: awa-bundle-custom.unmin.css (seção "=== FOOTER PROFESSIONAL ===")
