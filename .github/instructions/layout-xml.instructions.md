---
applyTo: "**/view/**/layout/**/*.xml"
---

# Regras para Layout XML (Magento 2)

## Nomeação de Arquivos
- Frontend: `routeid_controller_action.xml` (snake_case)
- Admin: `routeid_controller_action.xml` (snake_case)
- Default layout: `default.xml` (aplica a todas as páginas)
- Exemplos: `b2b_quote_index.xml`, `grupoawamotos_b2b_quote_respond.xml`

## Frontend — Página com Conteúdo
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      layout="2columns-left"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <title>Título da Página</title>
        <css src="GrupoAwamotos_ModuleName::css/styles.css"/>
    </head>
    <update handle="customer_account"/>
    <body>
        <referenceContainer name="content">
            <block class="GrupoAwamotos\ModuleName\Block\Entity\View"
                   name="module.entity.view"
                   template="GrupoAwamotos_ModuleName::entity/view.phtml"
                   cacheable="false"/>
        </referenceContainer>
    </body>
</page>
```

## Admin — Grid com UI Component
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <uiComponent name="grupoawamotos_module_entity_listing"/>
        </referenceContainer>
    </body>
</page>
```

## Admin — Form com Block
```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="GrupoAwamotos\ModuleName\Block\Adminhtml\Entity\Edit"
                   name="grupoawamotos_module_entity_edit"
                   template="GrupoAwamotos_ModuleName::entity/edit.phtml">
                <arguments>
                    <argument name="label" xsi:type="string" translate="true">Salvar</argument>
                </arguments>
            </block>
        </referenceContainer>
    </body>
</page>
```

## Adicionando JS/CSS
```xml
<head>
    <!-- CSS -->
    <css src="GrupoAwamotos_ModuleName::css/styles.css"/>
    <!-- JS via RequireJS -->
    <link src="GrupoAwamotos_ModuleName::js/component.js"/>
</head>
```

## Layouts Comuns do Magento
- `1column` — uma coluna (checkout, landing pages)
- `2columns-left` — duas colunas com sidebar esquerda (conta do cliente)
- `2columns-right` — duas colunas com sidebar direita
- `3columns` — três colunas

## Padrões Obrigatórios
- Schema XSD correto (`page_configuration.xsd`)
- `name` único para cada block (evitar conflito)
- Template path no formato `VendorName_ModuleName::path/template.phtml`
- `cacheable="false"` apenas quando necessário (conteúdo dinâmico por sessão)
- `translate="true"` em textos que devem ser traduzidos

## NUNCA
- Block sem `name` (causa conflito/sobrescrita acidental)
- Hardcodar HTML no layout XML — usar template PHTML
- Layout XML com lógica condicional (use Block/ViewModel para isso)
- Remover blocks do core sem necessidade (ex: `<referenceBlock name="..." remove="true"/>`)
- `cacheable="false"` em páginas que podem ser cacheadas
