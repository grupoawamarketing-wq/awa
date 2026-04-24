<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Model\ResourceModel\Followup as FollowupResource;
use Magento\Framework\Model\AbstractModel;

class Followup extends AbstractModel
{
    public const CONTACT_TYPES = [
        'whatsapp'   => 'WhatsApp',
        'phone'      => 'Telefone',
        'email'      => 'E-mail',
        'order'      => 'Pedido Manual',
        'quote'      => 'Orçamento',
        'recovery'   => 'Recuperação de Carrinho',
        'aftersale'  => 'Pós-venda',
        'visit'      => 'Visita',
    ];

    public const STATUSES = [
        'open'      => 'Em aberto',
        'done'      => 'Concluído',
        'scheduled' => 'Agendado',
    ];

    protected function _construct(): void
    {
        $this->_init(FollowupResource::class);
    }
}
