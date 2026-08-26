<?php
/**
 * 汇总 PHPUnit 结果（build/junit.xml + build/clover.xml）为 Markdown 报告。
 * 用法: php scripts/build-test-report.php > docs/test-report/report.md
 */

require __DIR__ . '/../vendor/autoload.php';

$junit = simplexml_load_file(__DIR__ . '/../build/junit.xml');
if ($junit === false) {
    fwrite(STDERR, "缺少 build/junit.xml，先运行 scripts/test-report.sh\n");
    exit(1);
}

$clover = simplexml_load_file(__DIR__ . '/../build/clover.xml');

// 各套件统计：只聚合带 file 属性的类级套件，按包分组
$suites = [];
$total = ['tests' => 0, 'assertions' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0];
foreach ($junit->xpath('//testsuite[@file]') as $suite) {
    $file = (string) $suite['file'];
    $pkg = preg_match('#/packages/([^/]+)/tests/#', $file, $m) ? $m[1] : 'tests/root';
    $s = [
        'tests' => (int) $suite['tests'],
        'assertions' => (int) $suite['assertions'],
        'failures' => (int) $suite['failures'],
        'errors' => (int) $suite['errors'],
        'skipped' => (int) $suite['skipped'],
    ];
    foreach ($s as $k => $v) {
        $suites[$pkg][$k] = ($suites[$pkg][$k] ?? 0) + $v;
        $total[$k] += $v;
    }
}

// 每个源文件的覆盖率（clover）
$files = [];
if ($clover !== false) {
    foreach ($clover->project->file as $file) {
        $path = (string) $file['name'];
        $loc = 0;
        $covered = 0;
        $statements = 0;
        foreach ($file->class as $class) {
            $m = $class->metrics;
            $statements += (int) $m['statements'];
            $covered += (int) $m['coveredstatements'];
        }
        $pct = $statements > 0 ? round($covered / $statements * 100, 1) : 100.0;
        $files[$path] = ['loc' => $loc, 'covered' => $covered, 'statements' => $statements, 'pct' => $pct];
    }
}

// 按包聚合覆盖率
$pkgCoverage = [];
foreach ($files as $path => $f) {
    $pkg = preg_match('#/packages/([^/]+)/(src|tests)/#', $path, $m)
        ? $m[1]
        : 'tests/root';
    $pkgCoverage[$pkg]['statements'] = ($pkgCoverage[$pkg]['statements'] ?? 0) + $f['statements'];
    $pkgCoverage[$pkg]['covered'] = ($pkgCoverage[$pkg]['covered'] ?? 0) + $f['covered'];
}

$pass = $total['failures'] + $total['errors'] === 0;

echo "# 测试报告\n\n";
echo "- 生成时间: " . date('Y-m-d H:i:s') . "\n";
echo "- PHP: " . PHP_VERSION . " | PHPUnit: " . \PHPUnit\Runner\Version::id() . "\n";
echo "- 套件数: " . count($suites) . " | 测试: {$total['tests']} | 断言: {$total['assertions']} | 失败: {$total['failures']} | 错误: {$total['errors']} | 跳过: {$total['skipped']}\n";
echo "- 结论: **" . ($pass ? "通过" : "未通过") . "**\n\n";

echo "## 按模块统计\n\n";
echo "| 模块 | 测试 | 断言 | 失败 | 错误 | 跳过 | 语句覆盖率 |\n";
echo "|---|---|---|---|---|---|---|\n";
foreach ($suites as $name => $s) {
    $pct = isset($pkgCoverage[$name]) && $pkgCoverage[$name]['statements'] > 0
        ? round($pkgCoverage[$name]['covered'] / $pkgCoverage[$name]['statements'] * 100, 1) . '%'
        : '-';
    $status = $s['failures'] + $s['errors'] > 0 ? '❌' : '✅';
    echo "| $status $name | {$s['tests']} | {$s['assertions']} | {$s['failures']} | {$s['errors']} | {$s['skipped']} | $pct |\n";
}
echo "\n## 整体覆盖率\n\n";
$allStmts = array_sum(array_column($pkgCoverage, 'statements'));
$allCov = array_sum(array_column($pkgCoverage, 'covered'));
if ($allStmts > 0) {
    echo "总语句: $allStmts | 已覆盖: $allCov | 覆盖率: **" . round($allCov / $allStmts * 100, 2) . "%**\n\n";
}
echo "HTML 覆盖率报告: `build/coverage/index.html`\n";
