<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\CatalogSearch\Controller\Result\Index;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;

/**
 * Sets a proper HTTP 404 status when Magento forwards a missing URL to the search result page.
 *
 * Magento's NoRouteHandler appends ?404=1 to the query string before forwarding to
 * catalogsearch/result. Without this plugin the response returns HTTP 200, causing
 * search engines to index the URL as valid content (soft 404).
 *
 * The original execute() is typed void, so $result will always be null.
 */
class Soft404Plugin
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly HttpResponse $response
    ) {}

    /**
     * @param Index $subject
     * @param mixed $result   Always null — execute() returns void
     * @return mixed
     */
    public function afterExecute(Index $subject, mixed $result): mixed
    {
        if ($this->request->getParam('404') === '1') {
            $this->response->setHttpResponseCode(404);
        }

        return $result;
    }
}
