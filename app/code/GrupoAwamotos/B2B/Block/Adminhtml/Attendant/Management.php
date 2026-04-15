<?php

/**
 * Bloco admin para gestão de atendentes B2B.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Adminhtml\Attendant;

use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Management extends Template
{
    private AttendantManager $attendantManager;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $attendant = null;

    public function __construct(
        Context $context,
        AttendantManager $attendantManager,
        array $data = []
    ) {
        $this->attendantManager = $attendantManager;
        parent::__construct($context, $data);
    }

    /**
     * Retorna os dados do atendente em edição.
     *
     * @return array<string, mixed>
     */
    public function getAttendant(): array
    {
        if ($this->attendant !== null) {
            return $this->attendant;
        }

        $attendantId = (int) $this->getRequest()->getParam('id');
        if ($attendantId <= 0) {
            $this->attendant = [];
            return $this->attendant;
        }

        $this->attendant = $this->attendantManager->getAttendantById($attendantId) ?? [];

        return $this->attendant;
    }

    public function isEditMode(): bool
    {
        return !empty($this->getAttendant()['attendant_id']);
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('grupoawamotos_b2b/attendant/save');
    }

    public function getIndexUrl(): string
    {
        return $this->getUrl('grupoawamotos_b2b/attendant/index');
    }
}
