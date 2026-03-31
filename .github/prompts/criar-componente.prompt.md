---
description: "Cria um módulo Magento 2 completo com Block, Template, Layout, JS e LESS"
agent: "agent"
tools:
  - codebase
  - changes
  - problems

---

Crie um módulo/componente frontend completo no Magento 2.

## O que criar:

### Block (Block/)
- Block class com métodos para fornecer dados ao template
- ViewModel se a lógica for complexa (`ViewModel/`)
- DI via construtor

### Template (view/frontend/templates/)
- PHTML com escape de output (`escapeHtml`, `escapeUrl`)
- Lógica mínima (apenas exibição)
- Sem ObjectManager direto

### Layout XML (view/frontend/layout/)
- Referência ao container correto
- Argumentos tipados
- ViewModel declarado via arguments

### JavaScript (view/frontend/web/js/)
- RequireJS module definition
- Knockout.js se for componente dinâmico
- jQuery via RequireJS (não CDN)
- `data-mage-init` ou `x-magento-init` no template

### Estilos (view/frontend/web/css/)
- LESS (não SCSS)
- Import via `_module.less` ou Layout XML
- Mobile-first e responsivo

### Checklist:
- [ ] `declare(strict_types=1)` em PHP
- [ ] Type hints em todos os métodos
- [ ] Output escapado no template
- [ ] JavaScript via RequireJS
- [ ] LESS compilando sem erros
- [ ] `php -l` sem erros
- [ ] Cache limpo e testado
