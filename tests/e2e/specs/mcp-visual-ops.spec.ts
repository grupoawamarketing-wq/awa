import path from 'path';
import { test, expect } from '@playwright/test';
import {
  DEFAULT_TARGETS,
  compareAgainstBaseline,
  detectVisualFindings,
  dismissCookieBanner,
  ensureDir,
  resolveAuditPaths,
  shouldFailBySeverity,
  waitPageStable,
  writeJsonReport,
  type VisualFinding,
} from '../helpers/mcp-visual-ops.helpers';

const ROOT_DIR = path.join(__dirname, '..');
const AUDIT_PATHS = resolveAuditPaths(ROOT_DIR);
const UPDATE_BASELINE = process.env.MCP_VISUAL_UPDATE_BASELINE === '1';
const FAIL_ON = (process.env.MCP_VISUAL_FAIL_ON as 'critical' | 'major' | 'none') || 'major';

test.describe('MCP Visual Ops - Automated Visual QA', () => {
  for (const target of DEFAULT_TARGETS) {
    test(`audit ${target.slug}`, async ({ page, browserName }, testInfo) => {
      const projectName = testInfo.project.name;
      const currentDir = path.join(AUDIT_PATHS.currentDir, projectName);
      const baselineDir = path.join(AUDIT_PATHS.baselineDir, projectName);
      ensureDir(currentDir);
      ensureDir(baselineDir);
      ensureDir(AUDIT_PATHS.reportDir);

      await page.goto(target.url, { waitUntil: 'domcontentloaded', timeout: 60_000 });
      await waitPageStable(page);
      await dismissCookieBanner(page);

      const screenshotName = `${target.slug}.png`;
      const screenshotPath = path.join(currentDir, screenshotName);
      const fullPath = path.join(currentDir, `${target.slug}.full.png`);
      const baselinePath = path.join(baselineDir, screenshotName);

      await page.screenshot({ path: screenshotPath, fullPage: false });
      await page.screenshot({ path: fullPath, fullPage: true });

      const baselineDiff = compareAgainstBaseline(screenshotPath, baselinePath, UPDATE_BASELINE);
      const evidence = [screenshotPath, fullPath];
      const findings: VisualFinding[] = await detectVisualFindings(
        page,
        target,
        browserName,
        projectName,
        evidence
      );

      if (baselineDiff.baselineMissing && !UPDATE_BASELINE) {
        findings.push({
          id: `${target.slug}-baseline-missing`,
          severity: 'minor',
          page: target.pageLabel,
          component: 'Baseline set',
          title: 'Baseline screenshot missing',
          description: 'No expected screenshot exists for comparison.',
          expected: 'Baseline file should exist for visual regression check.',
          actual: `Missing baseline at ${baselinePath}`,
          browser: browserName,
          device: projectName,
          evidence,
        });
      }

      if (baselineDiff.changed) {
        findings.push({
          id: `${target.slug}-visual-regression`,
          severity: 'major',
          page: target.pageLabel,
          component: 'Page screenshot',
          title: 'Visual regression against baseline',
          description: 'Current screenshot differs from expected baseline.',
          expected: `Match baseline ${baselinePath}`,
          actual: `Current screenshot differs: ${screenshotPath}`,
          browser: browserName,
          device: projectName,
          evidence,
          autofixRuleId: 'manual-review-required',
        });
      }

      const reportFile = path.join(
        AUDIT_PATHS.reportDir,
        `${projectName}-${target.slug}.finding.json`
      );

      writeJsonReport(reportFile, {
        target,
        browser: browserName,
        project: projectName,
        baseline: {
          file: baselinePath,
          changed: baselineDiff.changed,
          baselineMissing: baselineDiff.baselineMissing,
          updateMode: UPDATE_BASELINE,
        },
        findings,
        generatedAt: new Date().toISOString(),
      });

      await testInfo.attach(`mcp-${target.slug}-viewport`, {
        path: screenshotPath,
        contentType: 'image/png',
      });
      await testInfo.attach(`mcp-${target.slug}-full`, {
        path: fullPath,
        contentType: 'image/png',
      });
      await testInfo.attach(`mcp-${target.slug}-report`, {
        path: reportFile,
        contentType: 'application/json',
      });

      const mustFail = shouldFailBySeverity(findings, FAIL_ON);
      expect(
        mustFail,
        `Detected visual findings at severity threshold "${FAIL_ON}". Check ${reportFile}`
      ).toBe(false);
    });
  }
});
