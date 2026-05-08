<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ResetCircuitButton extends Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        /** @var \Magento\Backend\Block\Widget\Button $button */
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id'      => 'erp_reset_circuit_btn',
            'label'   => __('Resetar Circuito'),
            'class'   => 'action-default scalable',
            'onclick' => sprintf("erpResetCircuit('%s')", $this->getResetUrl()),
        ]);

        return $button->toHtml() . $this->_getScriptHtml();
    }

    /**
     * @return string
     */
    private function getResetUrl(): string
    {
        return $this->getUrl('erpintegration/sync/resetCircuit');
    }

    /**
     * @return string
     */
    private function _getScriptHtml(): string
    {
        return <<<HTML
<script>
function erpResetCircuit(url) {
    var btn = document.getElementById('erp_reset_circuit_btn');
    btn.disabled = true;
    btn.innerText = 'Resetando...';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerText = 'Resetar Circuito';
        alert(data.message || 'Concluido');
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerText = 'Resetar Circuito';
        alert('Erro ao comunicar com o servidor.');
    });
}
</script>
HTML;
    }
}
