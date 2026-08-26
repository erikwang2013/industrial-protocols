#!/usr/bin/env bash
# 生成测试报告并本地存储：
#   1. 运行全套测试（含 pcov 覆盖率）
#   2. 生成 build/junit.xml + build/clover.xml + build/coverage/ (HTML)
#   3. 汇总为 docs/test-report/report.md
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p build docs/test-report
vendor/bin/phpunit \
  --log-junit=build/junit.xml \
  --coverage-clover=build/clover.xml \
  --coverage-html=build/coverage \
  --coverage-text=build/coverage.txt

php scripts/build-test-report.php > docs/test-report/report.md
echo "==> 报告已生成: docs/test-report/report.md (HTML 覆盖率: build/coverage/index.html)"
