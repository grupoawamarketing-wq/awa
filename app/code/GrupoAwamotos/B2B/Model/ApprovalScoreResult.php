<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Api\Data\ApprovalScoreResultInterface;

class ApprovalScoreResult implements ApprovalScoreResultInterface
{
    private string $score;
    private string $reason;
    private int $suggestedGroupId;
    private bool $autoApprove;

    public function __construct(
        string $score,
        string $reason,
        int $suggestedGroupId,
        bool $autoApprove = false
    ) {
        $this->score = $score;
        $this->reason = $reason;
        $this->suggestedGroupId = $suggestedGroupId;
        $this->autoApprove = $autoApprove;
    }

    public function getScore(): string
    {
        return $this->score;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getSuggestedGroupId(): int
    {
        return $this->suggestedGroupId;
    }

    public function shouldAutoApprove(): bool
    {
        return $this->autoApprove;
    }
}
