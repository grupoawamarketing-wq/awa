#!/usr/bin/env node
import fs from 'fs';
import path from 'path';

function resolveE2ERoot() {
  const cwd = process.cwd();
  if (cwd.endsWith(path.join('tests', 'e2e'))) return cwd;
  return path.resolve(cwd, 'tests', 'e2e');
}

const rootDir = resolveE2ERoot();
const reportDir = path.join(rootDir, 'reports', 'mcp-visual');
const findingGlobSuffix = '.finding.json';
const outputJson = path.join(reportDir, 'mcp-visual-consolidated.json');
const outputMd = path.join(reportDir, 'mcp-visual-consolidated.md');

if (!fs.existsSync(reportDir)) {
  fs.mkdirSync(reportDir, { recursive: true });
}

const findingFiles = fs
  .readdirSync(reportDir)
  .filter((file) => file.endsWith(findingGlobSuffix))
  .sort();

const consolidated = {
  generatedAt: new Date().toISOString(),
  files: findingFiles,
  runs: [],
  totals: {
    critical: 0,
    major: 0,
    minor: 0,
    findings: 0,
  },
};

for (const file of findingFiles) {
  const fullPath = path.join(reportDir, file);
  const parsed = JSON.parse(fs.readFileSync(fullPath, 'utf8'));
  consolidated.runs.push(parsed);
  for (const finding of parsed.findings || []) {
    consolidated.totals.findings += 1;
    if (finding.severity === 'critical') consolidated.totals.critical += 1;
    else if (finding.severity === 'major') consolidated.totals.major += 1;
    else consolidated.totals.minor += 1;
  }
}

fs.writeFileSync(outputJson, JSON.stringify(consolidated, null, 2), 'utf8');

const md = [];
md.push('# MCP Visual Audit - Consolidated Report');
md.push('');
md.push(`- Generated at: ${consolidated.generatedAt}`);
md.push(`- Runs analyzed: ${consolidated.runs.length}`);
md.push(`- Findings: ${consolidated.totals.findings}`);
md.push(`- Critical: ${consolidated.totals.critical}`);
md.push(`- Major: ${consolidated.totals.major}`);
md.push(`- Minor: ${consolidated.totals.minor}`);
md.push('');

for (const run of consolidated.runs) {
  md.push(`## ${run.project} - ${run.target?.slug || 'unknown-target'}`);
  md.push(`- URL: ${run.target?.url || '-'}`);
  md.push(`- Baseline changed: ${run.baseline?.changed ? 'yes' : 'no'}`);
  md.push(`- Baseline missing: ${run.baseline?.baselineMissing ? 'yes' : 'no'}`);
  md.push('');
  const findings = run.findings || [];
  if (findings.length === 0) {
    md.push('- No findings');
    md.push('');
    continue;
  }
  for (const f of findings) {
    md.push(`- [${String(f.severity).toUpperCase()}] ${f.title}`);
    md.push(`  - Component: ${f.component}`);
    md.push(`  - Expected: ${f.expected}`);
    md.push(`  - Actual: ${f.actual}`);
    if (Array.isArray(f.evidence) && f.evidence.length > 0) {
      md.push(`  - Evidence: ${f.evidence.join(', ')}`);
    }
  }
  md.push('');
}

fs.writeFileSync(outputMd, `${md.join('\n')}\n`, 'utf8');

console.log(`[mcp-visual-report] JSON: ${outputJson}`);
console.log(`[mcp-visual-report] MD:   ${outputMd}`);
