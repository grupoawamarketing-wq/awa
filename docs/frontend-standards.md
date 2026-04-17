# Padroes Frontend (Magento)

## Objetivo
Padronizar layout XML, templates PHTML, JS e CSS/LESS para manter consistencia visual, acessibilidade e performance.

## Layout XML
- mantenha handles pequenos e com responsabilidade clara
- prefira adicionar `css` por handle quando a pagina for especifica
- evite incluir assets globais desnecessarios em paginas leves
- use `remove` com cuidado e documente motivo quando afetar bundles

## Templates PHTML
- escape output quando aplicavel
- evite logica de negocio no template
- prefira `ViewModel` para regras e dados
- use `x-magento-init` para inicializacao de JS

## JavaScript
- use RequireJS/AMD conforme padrao do Magento
- inicialize via `data-mage-init` / `text/x-magento-init`
- evite depender de seletor fragil; prefira IDs e data-attributes estaveis
- trate estados de erro e carregamento

## CSS/LESS
- prefira escopo por pagina quando possivel
- evite seletores globais agressivos
- mantenha arquitetura de camadas: base -> componentes -> pagina -> overrides
- valide impacto em bundles e critical path

## Acessibilidade
- labels associados a inputs
- `aria-live` apenas quando necessario
- foco visivel e navegação por teclado
- textos alternativos em imagens

## Performance
- evite bloquear render com JS desnecessario
- minimize css nao utilizado em paginas criticas
- use preload quando houver ganhos reais
- valide pagespeed e waterfall em paginas chave

## Checklist De Review Frontend
- [ ] nao quebrou header, minicart e busca
- [ ] nao regressou home, plp, pdp, carrinho e checkout
- [ ] JS inicializa corretamente no DOM esperado
- [ ] CSS esta escopado e nao vaza
- [ ] acessibilidade minima mantida
