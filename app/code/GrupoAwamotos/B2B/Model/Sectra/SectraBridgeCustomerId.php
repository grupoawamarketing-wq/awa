<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

/**
 * SQL/helper para customer_id exposto ao Sectra (CHAVE = erp_code quando definido).
 */
class SectraBridgeCustomerId
{
    public const OC_CUSTOMER_ID_OFFSET = 200000;

    /**
     * Expressão SQL: CHAVE Sectra usada em oc_order / oc_customer_b2b_confirmed.
     */
    public static function sqlExpression(
        string $orderAlias = 'so',
        string $mapAlias = 'map',
        string $erpAlias = 'erp_attr'
    ): string {
        return sprintf(
            'COALESCE(NULLIF(CAST(%s.value AS UNSIGNED), 0), %s.old_oc_customer_id, (%s.customer_id + %d))',
            $erpAlias,
            $mapAlias,
            $orderAlias,
            self::OC_CUSTOMER_ID_OFFSET
        );
    }

    public static function ocOrderIdExpression(string $orderAlias = 'so'): string
    {
        return sprintf('(%s.entity_id + %d)', $orderAlias, self::OC_CUSTOMER_ID_OFFSET);
    }
}
