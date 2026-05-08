import { execSync } from 'child_process';
import path from 'path';

/**
 * Global teardown — roda após todos os testes Playwright.
 * Garante que processos Chrome/Chromium não fiquem órfãos no servidor.
 * Corrige permissões de artefatos criados como root por subprocessos do browser.
 */
export default async function globalTeardown(): Promise<void> {
  // Mata qualquer processo Chrome/Chromium remanescente
  try {
    execSync(
      'pkill -9 -f "chrome-headless-shell|google-chrome|chromium" 2>/dev/null || true',
      { shell: '/bin/bash', stdio: 'ignore' },
    );
  } catch {
    // Ignorar erros — pode não haver processos para matar
  }

  // Corrige permissões de .playwright-artifacts-* criados como root.
  // Sem isso, o próximo run falha com EACCES ao tentar limpar artefatos antigos.
  try {
    const testResultsDir = path.resolve(__dirname, '..', 'test-results');
    const uid = process.getuid ? process.getuid() : 1007;
    const gid = process.getgid ? process.getgid() : 1007;
    execSync(
      `sudo chown -R ${uid}:${gid} "${testResultsDir}" 2>/dev/null || true`,
      { shell: '/bin/bash', stdio: 'ignore' },
    );
  } catch {
    // Melhor esforço — não bloquear o teardown se sudo não disponível
  }
}
