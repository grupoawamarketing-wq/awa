import { execSync } from 'child_process';
import path from 'path';

/**
 * Global teardown — roda após todos os testes Playwright.
 * Garante que processos de browser não fiquem órfãos no servidor.
 * Corrige permissões de artefatos criados como root por subprocessos do browser.
 */
export default async function globalTeardown(): Promise<void> {
  // Mata qualquer processo de browser remanescente
  try {
    execSync(
      'pkill -9 -f "chrome-headless-shell|google-chrome|chromium|firefox" 2>/dev/null || true',
      { shell: '/bin/bash', stdio: 'ignore' },
    );
  } catch {
    // Ignorar erros — pode não haver processos para matar
  }

  // Corrige permissões de artefatos criados como root.
  // Sem isso, o próximo run pode falhar com EACCES ao tentar limpar saídas antigas.
  try {
    const uid = process.getuid ? process.getuid() : 1007;
    const gid = process.getgid ? process.getgid() : 1007;
    const resultsDirs = [
      path.resolve(__dirname, '..', 'test-results'),
      path.resolve(__dirname, '..', 'test-results-safe'),
    ];

    for (const resultsDir of resultsDirs) {
      execSync(
        `sudo chown -R ${uid}:${gid} "${resultsDir}" 2>/dev/null || true`,
        { shell: '/bin/bash', stdio: 'ignore' },
      );
    }
  } catch {
    // Melhor esforço — não bloquear o teardown se sudo não disponível
  }
}
