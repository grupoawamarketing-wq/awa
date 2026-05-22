<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api\Data;

interface ApprovalScoreResultInterface
{
    public const SCORE_GREEN = 'green';
    public const SCORE_YELLOW = 'yellow';
    public const SCORE_RED = 'red';

    public function getScore(): string;

    public function getReason(): string;

    public function getSuggestedGroupId(): int;

    public function shouldAutoApprove(): bool;
}
