import { execSync } from 'child_process';

/**
 * Global setup — roda antes de todos os testes Playwright.
 * Mata processos de browsers órfãos de execuções anteriores para evitar
 * instabilidade causada por processos zombie consumindo memória.
 */
export default async function globalSetup(): Promise<void> {
  try {
    execSync('pkill -9 -f "chrome-headless-shell|google-chrome|chromium|firefox" 2>/dev/null || true', {
      shell: '/bin/bash',
      stdio: 'ignore',
    });
    // Small delay to let OS reclaim resources from killed processes
    await new Promise(resolve => setTimeout(resolve, 500));
  } catch {
    // Ignore — no leftover processes is fine
  }
}
