<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Test\Unit\Cron;

use GrupoAwamotos\Fitment\Cron\FallbackRebuild;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\Fitment\Cron\FallbackRebuild
 */
class FallbackRebuildTest extends TestCase
{
    private FallbackRebuild $cron;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cron = new FallbackRebuild($this->logger, '/tmp/__nonexistent_fallback_rebuild_test__.php');
    }

    /**
     * When script path does not exist, execute() logs "Script não encontrado".
     */
    public function testExecuteLogsErrorWhenScriptFails(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Script não encontrado'));

        $this->cron->execute();
    }

    public function testExecuteDoesNotThrow(): void
    {
        $this->cron->execute();
        $this->assertTrue(true, 'execute() must not throw even if script fails');
    }
}
