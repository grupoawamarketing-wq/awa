---
applyTo: "**/Controller/**/*.php"
---

# Regras para Controllers (Magento 2)

## Frontend Controller
```php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\ModuleName\Controller\Action;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $resultPageFactory
    ) {}

    public function execute(): Page
    {
        return $this->resultPageFactory->create();
    }
}
```

## Admin Controller
```php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\ModuleName\Controller\Adminhtml\Entity;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ModuleName::entity';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_ModuleName::entity');
        $resultPage->getConfig()->getTitle()->prepend(__('Entity List'));
        return $resultPage;
    }
}
```

## Padrões Obrigatórios

### Interfaces HTTP por Ação
- GET: `HttpGetActionInterface`
- POST: `HttpPostActionInterface`
- NUNCA usar a interface genérica `ActionInterface` sem especificar o método HTTP

### Admin Controllers
- Estender `Magento\Backend\App\Action`
- SEMPRE definir `ADMIN_RESOURCE` como constante pública (ACL)
- Chamar `parent::__construct($context)` no construtor
- Usar `setActiveMenu()` para navegação

### Frontend Controllers com POST
- Injetar `FormKeyValidator` para validar form key em ações POST
- Injetar `RequestInterface` para acessar dados do request
- Redirecionar após POST (pattern PRG — Post/Redirect/Get)

### Result Factories
- `PageFactory` para páginas HTML
- `JsonFactory` para respostas JSON (API/AJAX)
- `RedirectFactory` para redirecionamentos
- `RawFactory` para respostas raw

## Rotas
```xml
<!-- etc/frontend/routes.xml -->
<router id="standard">
    <route id="modulename" frontName="modulename">
        <module name="GrupoAwamotos_ModuleName"/>
    </route>
</router>

<!-- etc/adminhtml/routes.xml -->
<router id="admin">
    <route id="modulename" frontName="modulename">
        <module name="GrupoAwamotos_ModuleName"/>
    </route>
</router>
```

## NUNCA
- Controller sem interface HTTP específica (`HttpGetActionInterface` / `HttpPostActionInterface`)
- Admin controller sem `ADMIN_RESOURCE`
- Lógica de negócio no controller — delegar para Service/Model
- Acessar `$_POST` / `$_GET` diretamente — usar `RequestInterface`
- Retornar `void` do `execute()` — sempre retornar um Result
