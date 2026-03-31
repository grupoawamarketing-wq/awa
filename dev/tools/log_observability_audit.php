#!/usr/bin/env php
<?php
declare(strict_types=1);

date_default_timezone_set('UTC');

final class LogObservabilityAudit
{
    private array $config;
    private array $options;
    private array $severityOrder;

    public function __construct(array $config, array $argv)
    {
        $this->config = $config;
        $this->severityOrder = $config['severity_order'];
        $this->options = $this->parseOptions($argv);
    }

    public function run(): int
    {
        $files = $this->collectFiles();
        $analysis = $this->analyzeFiles($files);
        $alerts = $this->evaluateAlerts($analysis);
        $report = $this->buildMarkdownReport($analysis, $alerts);
        $json = $this->buildJsonReport($analysis, $alerts);

        if ($this->options['write_report'] !== null) {
            file_put_contents($this->options['write_report'], $report);
        }

        if ($this->options['write_json'] !== null) {
            file_put_contents(
                $this->options['write_json'],
                json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
            );
        }

        fwrite(STDOUT, $report);

        foreach ($alerts as $alert) {
            if ($alert['status'] === 'critical') {
                return 2;
            }
        }

        foreach ($alerts as $alert) {
            if ($alert['status'] === 'warning') {
                return 1;
            }
        }

        return 0;
    }

    private function parseOptions(array $argv): array
    {
        $options = [
            'groups' => $this->config['default_groups'],
            'levels' => array_keys($this->severityOrder),
            'contains' => null,
            'since' => null,
            'until' => null,
            'max_patterns' => 10,
            'max_cron' => 10,
            'write_report' => null,
            'write_json' => null,
        ];

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '--help') {
                $this->printHelp();
                exit(0);
            }

            if (!str_starts_with($argument, '--')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, null);

            if ($key === 'groups' && $value !== null && $value !== '') {
                $options['groups'] = array_values(array_filter(array_map('trim', explode(',', $value))));
            }

            if ($key === 'levels' && $value !== null && $value !== '') {
                $options['levels'] = array_values(array_filter(array_map(
                    static fn(string $level): string => strtoupper(trim($level)),
                    explode(',', $value)
                )));
            }

            if ($key === 'contains' && $value !== null && $value !== '') {
                $options['contains'] = $value;
            }

            if ($key === 'since' && $value !== null && $value !== '') {
                $options['since'] = new DateTimeImmutable($value);
            }

            if ($key === 'until' && $value !== null && $value !== '') {
                $options['until'] = new DateTimeImmutable($value);
            }

            if ($key === 'max-patterns' && $value !== null) {
                $options['max_patterns'] = max(1, (int) $value);
            }

            if ($key === 'max-cron' && $value !== null) {
                $options['max_cron'] = max(1, (int) $value);
            }

            if ($key === 'write-report' && $value !== null && $value !== '') {
                $options['write_report'] = $value;
            }

            if ($key === 'write-json' && $value !== null && $value !== '') {
                $options['write_json'] = $value;
            }
        }

        return $options;
    }

    private function printHelp(): void
    {
        $message = <<<TXT
Uso:
  php dev/tools/log_observability_audit.php [opções]

Opções:
  --groups=application,erp,database,third_party,server
  --levels=WARNING,ERROR,CRITICAL
  --contains=texto
  --since=2026-03-28T00:00:00+00:00
  --until=2026-03-29T23:59:59+00:00
  --max-patterns=10
  --max-cron=10
  --write-report=LOG_OBSERVABILITY_REPORT.md
  --write-json=LOG_OBSERVABILITY_REPORT.json
  --help

TXT;

        fwrite(STDOUT, $message);
    }

    private function collectFiles(): array
    {
        $files = [];

        foreach ($this->options['groups'] as $group) {
            if (!isset($this->config['source_groups'][$group])) {
                continue;
            }

            foreach ($this->config['source_groups'][$group] as $pattern) {
                $matches = glob($pattern) ?: [];

                if ($matches === [] && !str_contains($pattern, '*') && file_exists($pattern)) {
                    $matches = [$pattern];
                }

                foreach ($matches as $path) {
                    if (is_dir($path)) {
                        continue;
                    }

                    if (preg_match('/\.(sql|pid|txt|html|json)$/', $path) === 1) {
                        continue;
                    }

                    if (str_contains($path, '.bak')) {
                        continue;
                    }

                    $files[$path] = $group;
                }
            }
        }

        ksort($files);

        return $files;
    }

    private function analyzeFiles(array $files): array
    {
        $analysis = [
            'generated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'filters' => [
                'groups' => $this->options['groups'],
                'levels' => $this->options['levels'],
                'contains' => $this->options['contains'],
                'since' => $this->options['since']?->format(DateTimeInterface::ATOM),
                'until' => $this->options['until']?->format(DateTimeInterface::ATOM),
            ],
            'files' => [],
            'events_by_group_level' => [],
            'events_by_day' => [],
            'patterns' => [],
            'patterns_recent' => [],
            'cron' => [],
            'config_checks' => [],
            'unreadable_sources' => [],
            'empty_sources' => [],
            'oversized_sources' => [],
            'event_totals' => [
                'lines_scanned' => 0,
                'events_matched' => 0,
            ],
        ];

        $patternCounter = [];
        $patternRecentCounter = [];
        $patternExamples = [];
        $recentCutoff = new DateTimeImmutable('-2 days');
        $cronStats = [];

        foreach ($files as $path => $group) {
            $meta = [
                'group' => $group,
                'path' => $path,
                'basename' => basename($path),
                'size_bytes' => file_exists($path) ? (int) filesize($path) : 0,
                'mtime' => file_exists($path) ? date(DateTimeInterface::ATOM, (int) filemtime($path)) : null,
                'lines_scanned' => 0,
                'events_matched' => 0,
                'levels' => [],
                'status' => 'ok',
            ];

            if (!is_readable($path)) {
                $meta['status'] = 'unreadable';
                $analysis['files'][] = $meta;
                $analysis['unreadable_sources'][] = $path;
                continue;
            }

            if ($meta['size_bytes'] === 0) {
                $analysis['empty_sources'][] = $path;
            }

            if ($meta['size_bytes'] >= 10 * 1024 * 1024) {
                $analysis['oversized_sources'][] = [
                    'path' => $path,
                    'size_bytes' => $meta['size_bytes'],
                ];
            }

            $handle = $this->openFileHandle($path);

            if (!is_resource($handle)) {
                $meta['status'] = 'unreadable';
                $analysis['files'][] = $meta;
                $analysis['unreadable_sources'][] = $path;
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $meta['lines_scanned']++;
                $analysis['event_totals']['lines_scanned']++;

                $event = $this->parseLine($line);

                if ($event === null) {
                    continue;
                }

                if (!$this->matchesFilters($event)) {
                    continue;
                }

                $meta['events_matched']++;
                $analysis['event_totals']['events_matched']++;
                $meta['levels'][$event['level']] = ($meta['levels'][$event['level']] ?? 0) + 1;
                $analysis['events_by_group_level'][$group][$event['level']] = ($analysis['events_by_group_level'][$group][$event['level']] ?? 0) + 1;

                if ($event['timestamp'] !== null) {
                    $day = $event['timestamp']->format('Y-m-d');
                    $analysis['events_by_day'][$day][$event['level']] = ($analysis['events_by_day'][$day][$event['level']] ?? 0) + 1;
                }

                if ($this->severityOrder[$event['level']] >= $this->severityOrder['WARNING']) {
                    $patternKey = $event['level'] . '|' . $event['normalized_message'];
                    $patternCounter[$patternKey] = ($patternCounter[$patternKey] ?? 0) + 1;
                    $patternExamples[$patternKey] = $patternExamples[$patternKey] ?? $event['message'];

                    if ($event['timestamp'] !== null && $event['timestamp'] >= $recentCutoff) {
                        $patternRecentCounter[$patternKey] = ($patternRecentCounter[$patternKey] ?? 0) + 1;
                    }
                }

                $cronStat = $this->extractCronStat($line, $event['timestamp']);

                if ($cronStat !== null) {
                    $job = $cronStat['job'];

                    if (!isset($cronStats[$job])) {
                        $cronStats[$job] = [
                            'job' => $job,
                            'count' => 0,
                            'total_seconds' => 0.0,
                            'avg_seconds' => 0.0,
                            'max_seconds' => 0.0,
                            'last_seen' => null,
                        ];
                    }

                    $cronStats[$job]['count']++;
                    $cronStats[$job]['total_seconds'] += $cronStat['seconds'];
                    $cronStats[$job]['avg_seconds'] = $cronStats[$job]['total_seconds'] / $cronStats[$job]['count'];
                    $cronStats[$job]['max_seconds'] = max($cronStats[$job]['max_seconds'], $cronStat['seconds']);
                    $cronStats[$job]['last_seen'] = $cronStat['timestamp']?->format(DateTimeInterface::ATOM);
                }
            }

            fclose($handle);
            ksort($meta['levels']);
            $analysis['files'][] = $meta;
        }

        usort(
            $analysis['files'],
            static fn(array $left, array $right): int => strcmp($left['path'], $right['path'])
        );

        $analysis['patterns'] = $this->buildPatternList($patternCounter, $patternExamples, $this->options['max_patterns']);
        $analysis['patterns_recent'] = $this->buildPatternList($patternRecentCounter, $patternExamples, $this->options['max_patterns']);
        $analysis['cron'] = array_values($cronStats);

        usort(
            $analysis['cron'],
            static fn(array $left, array $right): int => $right['max_seconds'] <=> $left['max_seconds']
        );

        $analysis['cron'] = array_slice($analysis['cron'], 0, $this->options['max_cron']);
        $analysis['config_checks'] = $this->analyzeConfigChecks();
        ksort($analysis['events_by_group_level']);
        ksort($analysis['events_by_day']);

        return $analysis;
    }

    private function openFileHandle(string $path)
    {
        if (str_ends_with($path, '.gz')) {
            return fopen('compress.zlib://' . $path, 'rb');
        }

        return fopen($path, 'rb');
    }

    private function parseLine(string $line): ?array
    {
        $timestamp = $this->extractTimestamp($line);
        $level = $this->extractLevel($line);

        if ($level === null) {
            return null;
        }

        $message = $this->extractMessage($line);

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'normalized_message' => $this->normalizeMessage($message),
        ];
    }

    private function matchesFilters(array $event): bool
    {
        if (!in_array($event['level'], $this->options['levels'], true)) {
            return false;
        }

        if ($this->options['contains'] !== null && stripos($event['message'], $this->options['contains']) === false) {
            return false;
        }

        if ($this->options['since'] instanceof DateTimeImmutable && $event['timestamp'] instanceof DateTimeImmutable && $event['timestamp'] < $this->options['since']) {
            return false;
        }

        if ($this->options['until'] instanceof DateTimeImmutable && $event['timestamp'] instanceof DateTimeImmutable && $event['timestamp'] > $this->options['until']) {
            return false;
        }

        return true;
    }

    private function extractTimestamp(string $line): ?DateTimeImmutable
    {
        if (preg_match('/^\[([^\]]+)\]/', $line, $matches) === 1) {
            try {
                return new DateTimeImmutable($matches[1]);
            } catch (Throwable) {
                return null;
            }
        }

        if (preg_match('/^(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches) === 1) {
            try {
                return new DateTimeImmutable($matches[1] . ' UTC');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function extractLevel(string $line): ?string
    {
        if (preg_match('/\]\s+[A-Za-z0-9_.-]+\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/\[(emerg|alert|crit|error|warn|notice|info|debug)\]/i', $line, $matches) === 1) {
            return match (strtolower($matches[1])) {
                'emerg' => 'EMERGENCY',
                'alert' => 'ALERT',
                'crit' => 'CRITICAL',
                'error' => 'ERROR',
                'warn' => 'WARNING',
                'notice' => 'NOTICE',
                'info' => 'INFO',
                'debug' => 'DEBUG',
            };
        }

        if (preg_match('/\b(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)\b/', $line, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function extractMessage(string $line): string
    {
        if (preg_match('/^\[[^\]]+\]\s+[A-Za-z0-9_.-]+\.([A-Z]+):\s*(.*)$/', trim($line), $matches) === 1) {
            return trim($matches[2]);
        }

        if (preg_match('/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}\s+\[[^\]]+\]\s+\d+#\d+:\s*(.*)$/', trim($line), $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($line);
    }

    private function normalizeMessage(string $message): string
    {
        $normalized = $message;
        $normalized = preg_replace('/\b[0-9a-f]{8}-[0-9a-f-]{27,}\b/i', '<uuid>', $normalized) ?? $normalized;
        $normalized = preg_replace('/0x[0-9a-f]+/i', '<hex>', $normalized) ?? $normalized;
        $normalized = preg_replace('/"[^"]*"/', '"<str>"', $normalized) ?? $normalized;
        $normalized = preg_replace("/'[^']*'/", '\'<str>\'', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d{4,}\b/', '<n>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d+\b/', '<n>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return mb_substr(trim($normalized), 0, 220);
    }

    private function extractCronStat(string $line, ?DateTimeImmutable $timestamp): ?array
    {
        if (preg_match('/Cron Job ([^ ]+) is successfully finished\. Statistics: \{"sum":([0-9.eE+-]+)/', $line, $matches) !== 1) {
            return null;
        }

        return [
            'job' => $matches[1],
            'seconds' => (float) $matches[2],
            'timestamp' => $timestamp,
        ];
    }

    private function buildPatternList(array $counter, array $examples, int $limit): array
    {
        arsort($counter);
        $items = [];

        foreach (array_slice($counter, 0, $limit, true) as $key => $count) {
            [$level, $pattern] = explode('|', $key, 2);
            $items[] = [
                'level' => $level,
                'count' => $count,
                'pattern' => $pattern,
                'example' => $examples[$key] ?? $pattern,
            ];
        }

        return $items;
    }

    private function evaluateAlerts(array $analysis): array
    {
        $alerts = [];
        $recentEvents = $this->collectRecentEvents($analysis);

        foreach ($this->config['alerts'] as $rule) {
            $value = 0.0;

            if ($rule['metric'] === 'level') {
                $value = (float) ($recentEvents['levels'][$rule['level']] ?? 0);
            }

            if ($rule['metric'] === 'pattern') {
                foreach ($recentEvents['patterns'] as $message => $count) {
                    if (str_contains($message, $rule['match'])) {
                        $value += (float) $count;
                    }
                }
            }

            if ($rule['metric'] === 'cron_max_seconds') {
                $value = $analysis['cron'][0]['max_seconds'] ?? 0.0;
            }

            if ($rule['metric'] === 'unreadable_sources') {
                $families = [];

                foreach ($analysis['unreadable_sources'] as $path) {
                    $families[$this->normalizeSourceFamily($path)] = true;
                }

                $value = (float) count($families);
            }

            if ($rule['metric'] === 'config_drift') {
                $value = (float) count(array_filter(
                    $analysis['config_checks'],
                    static fn(array $check): bool => $check['status'] === 'drift'
                ));
            }

            $status = 'ok';

            if ($value >= (float) $rule['critical_threshold']) {
                $status = 'critical';
            } elseif ($value >= (float) $rule['warning_threshold']) {
                $status = 'warning';
            }

            $alerts[] = [
                'name' => $rule['name'],
                'metric' => $rule['metric'],
                'value' => $value,
                'status' => $status,
                'warning_threshold' => $rule['warning_threshold'],
                'critical_threshold' => $rule['critical_threshold'],
            ];
        }

        return $alerts;
    }

    private function collectRecentEvents(array $analysis): array
    {
        $levels = [];
        $patterns = [];
        $cutoff = new DateTimeImmutable('-2 days');

        foreach ($analysis['files'] as $file) {
            if ($file['status'] !== 'ok') {
                continue;
            }
        }

        $files = $this->collectFiles();

        foreach ($files as $path => $group) {
            if (!is_readable($path)) {
                continue;
            }

            $handle = $this->openFileHandle($path);

            if (!is_resource($handle)) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $event = $this->parseLine($line);

                if ($event === null || $event['timestamp'] === null || $event['timestamp'] < $cutoff) {
                    continue;
                }

                $levels[$event['level']] = ($levels[$event['level']] ?? 0) + 1;
                $patterns[$event['message']] = ($patterns[$event['message']] ?? 0) + 1;
            }

            fclose($handle);
        }

        return [
            'levels' => $levels,
            'patterns' => $patterns,
        ];
    }

    private function buildMarkdownReport(array $analysis, array $alerts): string
    {
        $lines = [];
        $lines[] = '# Log Observability Audit';
        $lines[] = '';
        $lines[] = '- Generated at: ' . $analysis['generated_at'];
        $lines[] = '- Groups: ' . implode(', ', $analysis['filters']['groups']);
        $lines[] = '- Levels: ' . implode(', ', $analysis['filters']['levels']);
        $lines[] = '- Contains filter: ' . ($analysis['filters']['contains'] ?? 'none');
        $lines[] = '- Since: ' . ($analysis['filters']['since'] ?? 'not set');
        $lines[] = '- Until: ' . ($analysis['filters']['until'] ?? 'not set');
        $lines[] = '';
        $lines[] = '## Executive Summary';
        $lines[] = '';
        $lines[] = '- Lines scanned: ' . number_format($analysis['event_totals']['lines_scanned'], 0, ',', '.');
        $lines[] = '- Events matched: ' . number_format($analysis['event_totals']['events_matched'], 0, ',', '.');
        $lines[] = '- Unreadable sources: ' . count($analysis['unreadable_sources']);
        $lines[] = '- Empty sources: ' . count($analysis['empty_sources']);
        $lines[] = '- Oversized sources (>10 MB): ' . count($analysis['oversized_sources']);
        $lines[] = '';
        $lines[] = '## Source Coverage';
        $lines[] = '';
        $lines[] = '| Group | File | Status | Size MB | Matched Events | Levels |';
        $lines[] = '|---|---|---|---:|---:|---|';

        foreach ($analysis['files'] as $file) {
            $levels = [];

            foreach ($file['levels'] as $level => $count) {
                $levels[] = $level . ':' . $count;
            }

            $lines[] = sprintf(
                '| %s | %s | %s | %.2f | %d | %s |',
                $file['group'],
                $file['basename'],
                $file['status'],
                $file['size_bytes'] / 1024 / 1024,
                $file['events_matched'],
                $levels === [] ? '—' : implode(', ', $levels)
            );
        }

        $lines[] = '';
        $lines[] = '## Severity by Group';
        $lines[] = '';
        $lines[] = '| Group | WARNING | ERROR | CRITICAL |';
        $lines[] = '|---|---:|---:|---:|';

        foreach ($analysis['events_by_group_level'] as $group => $levels) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d |',
                $group,
                $levels['WARNING'] ?? 0,
                $levels['ERROR'] ?? 0,
                $levels['CRITICAL'] ?? 0
            );
        }

        $lines[] = '';
        $lines[] = '## Alert Status';
        $lines[] = '';
        $lines[] = '| Rule | Status | Value | Warning | Critical |';
        $lines[] = '|---|---|---:|---:|---:|';

        foreach ($alerts as $alert) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $alert['name'],
                strtoupper($alert['status']),
                $this->formatMetricValue($alert['value']),
                $this->formatMetricValue((float) $alert['warning_threshold']),
                $this->formatMetricValue((float) $alert['critical_threshold'])
            );
        }

        $lines[] = '';
        $lines[] = '## Recurring Patterns';
        $lines[] = '';
        $lines[] = '| Level | Count | Pattern |';
        $lines[] = '|---|---:|---|';

        foreach ($analysis['patterns'] as $pattern) {
            $lines[] = sprintf(
                '| %s | %d | %s |',
                $pattern['level'],
                $pattern['count'],
                str_replace('|', '\\|', $pattern['pattern'])
            );
        }

        $lines[] = '';
        $lines[] = '## Recent Patterns (Last 48h)';
        $lines[] = '';
        $lines[] = '| Level | Count | Pattern |';
        $lines[] = '|---|---:|---|';

        foreach ($analysis['patterns_recent'] as $pattern) {
            $lines[] = sprintf(
                '| %s | %d | %s |',
                $pattern['level'],
                $pattern['count'],
                str_replace('|', '\\|', $pattern['pattern'])
            );
        }

        $lines[] = '';
        $lines[] = '## Trend Visualization';
        $lines[] = '';
        $lines[] = '| Day | Warn+Err+Crit | Trend |';
        $lines[] = '|---|---:|---|';

        $trendTotals = [];

        foreach ($analysis['events_by_day'] as $day => $levels) {
            $trendTotals[$day] = ($levels['WARNING'] ?? 0) + ($levels['ERROR'] ?? 0) + ($levels['CRITICAL'] ?? 0);
        }

        $trendMax = $trendTotals === [] ? 1 : max($trendTotals);

        foreach ($trendTotals as $day => $total) {
            if ($total === 0) {
                continue;
            }

            $lines[] = sprintf(
                '| %s | %d | %s |',
                $day,
                $total,
                $this->renderBar($total, $trendMax)
            );
        }

        $lines[] = '';
        $lines[] = '## Cron Runtime Hotspots';
        $lines[] = '';
        $lines[] = '| Cron Job | Runs | Avg s | Max s | Last Seen |';
        $lines[] = '|---|---:|---:|---:|---|';

        foreach ($analysis['cron'] as $cron) {
            $lines[] = sprintf(
                '| %s | %d | %.3f | %.3f | %s |',
                $cron['job'],
                $cron['count'],
                $cron['avg_seconds'],
                $cron['max_seconds'],
                $cron['last_seen'] ?? '—'
            );
        }

        $lines[] = '';
        $lines[] = '## Configuration Drift';
        $lines[] = '';
        $lines[] = '| Check | Status | Details |';
        $lines[] = '|---|---|---|';

        foreach ($analysis['config_checks'] as $check) {
            $lines[] = sprintf(
                '| %s | %s | %s |',
                $check['name'],
                strtoupper($check['status']),
                str_replace('|', '\\|', $check['details'])
            );
        }

        $lines[] = '';
        $lines[] = '## Findings';
        $lines[] = '';

        foreach ($this->buildFindings($analysis, $alerts) as $finding) {
            $lines[] = '- ' . $finding;
        }

        $lines[] = '';
        $lines[] = '## Recommendations';
        $lines[] = '';

        foreach ($this->buildRecommendations($analysis, $alerts) as $recommendation) {
            $lines[] = '- ' . $recommendation;
        }

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function buildJsonReport(array $analysis, array $alerts): array
    {
        return [
            'meta' => [
                'generated_at' => $analysis['generated_at'],
                'filters' => $analysis['filters'],
            ],
            'summary' => $analysis['event_totals'],
            'config_checks' => $analysis['config_checks'],
            'files' => $analysis['files'],
            'severity_by_group' => $analysis['events_by_group_level'],
            'events_by_day' => $analysis['events_by_day'],
            'alerts' => $alerts,
            'patterns' => $analysis['patterns'],
            'patterns_recent' => $analysis['patterns_recent'],
            'cron' => $analysis['cron'],
            'findings' => $this->buildFindings($analysis, $alerts),
            'recommendations' => $this->buildRecommendations($analysis, $alerts),
        ];
    }

    private function buildFindings(array $analysis, array $alerts): array
    {
        $findings = [];

        if ($analysis['patterns_recent'] !== []) {
            $topRecent = $analysis['patterns_recent'][0];
            $findings[] = sprintf(
                'Padrão mais recorrente nas últimas 48h: %s (%d ocorrências).',
                $topRecent['pattern'],
                $topRecent['count']
            );
        }

        if ($analysis['cron'] !== []) {
            $topCron = $analysis['cron'][0];
            $findings[] = sprintf(
                'Maior gargalo de cron detectado em %s com pico de %.3f s e média de %.3f s.',
                $topCron['job'],
                $topCron['max_seconds'],
                $topCron['avg_seconds']
            );
        }

        if ($analysis['oversized_sources'] !== []) {
            $largest = $analysis['oversized_sources'][0];
            $findings[] = sprintf(
                'Fonte volumosa encontrada: %s com %.2f MB, indicando alto ruído operacional.',
                basename($largest['path']),
                $largest['size_bytes'] / 1024 / 1024
            );
        }

        if ($analysis['unreadable_sources'] !== []) {
            $families = [];

            foreach ($analysis['unreadable_sources'] as $path) {
                $families[$this->normalizeSourceFamily($path)] = true;
            }

            $findings[] = 'Há fontes críticas sem leitura no contexto atual: ' . implode(', ', array_keys($families)) . '.';
        }

        foreach ($analysis['config_checks'] as $check) {
            if ($check['status'] === 'drift') {
                $findings[] = 'Drift operacional detectado: ' . $check['details'] . '.';
            }
        }

        foreach ($alerts as $alert) {
            if ($alert['status'] === 'critical') {
                $findings[] = sprintf(
                    'Alerta crítico disparado: %s com valor %s.',
                    $alert['name'],
                    $this->formatMetricValue($alert['value'])
                );
            }
        }

        if ($analysis['empty_sources'] !== []) {
            $findings[] = 'Fontes vazias identificadas: ' . implode(', ', $analysis['empty_sources']) . '.';
        }

        return array_values(array_unique($findings));
    }

    private function buildRecommendations(array $analysis, array $alerts): array
    {
        $recommendations = [];

        foreach ($analysis['patterns_recent'] as $pattern) {
            if (str_contains($pattern['pattern'], 'SearchAutocompleteProductPlugin::afterMap')) {
                $recommendations[] = 'Validar e publicar a correção do plugin de autocomplete do Fitment antes do próximo ciclo de indexação.';
            }

            if (str_contains($pattern['pattern'], 'queue:consumers')) {
                $recommendations[] = 'Revisar chamadas para queue:consumers no cron e alinhar com os namespaces realmente disponíveis nesta instalação Magento.';
            }

            if (str_contains($pattern['pattern'], 'Stock anomalies detected during sync')) {
                $recommendations[] = 'Auditar regras de sincronização de estoque do ERP para SKUs com variações extremas e confirmar se há mudanças legítimas de inventário.';
            }

            if (str_contains($pattern['pattern'], 'B2B clients missing Sectra registration')) {
                $recommendations[] = 'Tratar a fila de clientes B2B sem registro no Sectra e liberar conexão de escrita ou processo assistido para o ERP.';
            }
        }

        foreach ($alerts as $alert) {
            if ($alert['name'] === 'unreadable-source' && $alert['status'] !== 'ok') {
                $recommendations[] = 'Conceder leitura controlada aos logs de nginx/mysql para completar a correlação entre aplicação, banco e servidor.';
            }

            if ($alert['name'] === 'critical-events' && $alert['status'] !== 'ok') {
                $recommendations[] = 'Criar notificação imediata para CRITICAL em janela de 48h e direcionar incidentes para triagem prioritária.';
            }

            if ($alert['name'] === 'cron-runtime' && $alert['status'] !== 'ok') {
                $recommendations[] = 'Quebrar jobs longos em lotes menores e monitorar tempo de execução de cron acima de 60 segundos.';
            }

            if ($alert['name'] === 'queue-strategy-drift' && $alert['status'] !== 'ok') {
                $recommendations[] = 'Alinhar env.php, Supervisor e documentação para um único mecanismo de consumo da fila ERP.';
            }
        }

        if ($analysis['oversized_sources'] !== []) {
            $recommendations[] = 'Aplicar rotação e redução de verbosidade no debug.log para diminuir custo de análise e retenção.';
        }

        if ($analysis['empty_sources'] !== []) {
            $recommendations[] = 'Confirmar se logs vazios representam ausência real de eventos ou telemetria desabilitada para banco/serviços externos.';
        }

        $recommendations[] = 'Executar esta auditoria por cron ou pipeline diário e armazenar os artefatos Markdown/JSON para tendência histórica.';

        return array_values(array_unique($recommendations));
    }

    private function renderBar(int $value, int $max): string
    {
        if ($max <= 0 || $value <= 0) {
            return '░';
        }

        $width = max(1, (int) round(($value / $max) * 18));

        return str_repeat('█', $width);
    }

    private function formatMetricValue(float $value): string
    {
        if (abs($value - floor($value)) < 0.00001) {
            return (string) (int) $value;
        }

        return number_format($value, 3, '.', '');
    }

    private function normalizeSourceFamily(string $path): string
    {
        $normalized = preg_replace('/\.\d+\.gz$/', '', $path) ?? $path;
        $normalized = preg_replace('/\.\d+$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\.gz$/', '', $normalized) ?? $normalized;

        return $normalized;
    }

    private function analyzeConfigChecks(): array
    {
        $checks = [];
        $envPath = getcwd() . '/app/etc/env.php';
        $supervisorPath = '/etc/supervisor/conf.d/magento-consumers.conf';
        $envCronRun = null;

        if (is_readable($envPath)) {
            $env = require $envPath;
            $envCronRun = (bool) ($env['cron_consumers_runner']['cron_run'] ?? false);
            $checks[] = [
                'name' => 'env-cron-consumers-runner',
                'status' => $envCronRun ? 'drift' : 'ok',
                'details' => 'cron_consumers_runner.cron_run=' . ($envCronRun ? 'true' : 'false'),
            ];
        } else {
            $checks[] = [
                'name' => 'env-cron-consumers-runner',
                'status' => 'unknown',
                'details' => 'app/etc/env.php indisponível para leitura',
            ];
        }

        if (is_readable($supervisorPath)) {
            $content = (string) file_get_contents($supervisorPath);
            $autostartEnabled = preg_match('/^\s*autostart\s*=\s*true\s*$/mi', $content) === 1;
            $autorestartEnabled = preg_match('/^\s*autorestart\s*=\s*true\s*$/mi', $content) === 1;
            $status = (!$envCronRun && ($autostartEnabled || $autorestartEnabled)) ? 'drift' : 'ok';
            $checks[] = [
                'name' => 'supervisor-magento-consumers',
                'status' => $status,
                'details' => sprintf(
                    'supervisor autostart=%s autorestart=%s em %s',
                    $autostartEnabled ? 'true' : 'false',
                    $autorestartEnabled ? 'true' : 'false',
                    $supervisorPath
                ),
            ];
        } else {
            $checks[] = [
                'name' => 'supervisor-magento-consumers',
                'status' => 'unknown',
                'details' => 'Arquivo Supervisor não acessível: ' . $supervisorPath,
            ];
        }

        return $checks;
    }
}

$config = require __DIR__ . '/log_observability_rules.php';
$tool = new LogObservabilityAudit($config, $argv);
exit($tool->run());
