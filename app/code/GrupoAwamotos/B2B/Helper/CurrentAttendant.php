<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Helper;

use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\ResourceConnection;

/**
 * Verifica se o usuário admin logado é um atendente B2B e retorna seu ID.
 *
 * A tabela grupoawamotos_b2b_attendants vincula admin_user_id ao atendente.
 * Resultados são cacheados por instância para evitar múltiplas queries por request.
 */
class CurrentAttendant
{
    private bool $resolved = false;
    private ?int $attendantId = null;

    public function __construct(
        private readonly AdminSession $adminSession,
        private readonly ResourceConnection $resource
    ) {}

    /**
     * Retorna true se o usuário admin logado é um atendente ativo.
     */
    public function isAttendant(): bool
    {
        return $this->getId() !== null;
    }

    /**
     * Retorna o attendant_id do usuário logado, ou null se não for atendente.
     */
    public function getId(): ?int
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $this->attendantId = $this->resolveAttendantId();
        }

        return $this->attendantId;
    }

    private function resolveAttendantId(): ?int
    {
        $user = $this->adminSession->getUser();
        if (!$user || !$user->getId()) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_b2b_attendants');

        $attendantId = $connection->fetchOne(
            $connection->select()
                ->from($table, 'attendant_id')
                ->where('admin_user_id = ?', (int) $user->getId())
                ->where('is_active = ?', 1)
                ->limit(1)
        );

        return $attendantId ? (int) $attendantId : null;
    }
}
