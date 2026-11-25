#!/bin/sh
# Dependency-free policy tests, requires PHP CLI, Node, global TypeScript and shell.
set -eu
cd "$(dirname "$0")/.."
find backend-yii -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
php -l backend-yii/yii
php backend-yii/tests/policy.php
php backend-yii/tests/master-policy.php
php backend-yii/tests/visit-policy.php
php backend-yii/tests/clinical-policy.php
php backend-yii/tests/ancillary-policy.php
php backend-yii/tests/billing-policy.php
php backend-yii/tests/admin-policy.php
php backend-yii/tests/qa-policy.php
php backend-yii/tests/browser-session-policy.php
for f in scripts/*.sh database/init/*.sh; do sh -n "$f"; done
printf 'PASS: shell syntax\n'
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT HUP INT TERM
tsc --strict --target ES2022 --module commonjs --outDir "$tmp" backend-node/src/policy.ts frontend/src/lib/roles.ts frontend/src/lib/clinical.ts frontend/src/lib/billing.ts backend-node/src/report-policy.ts frontend/src/lib/reporting.ts frontend/src/lib/presentation.ts frontend/src/lib/workflow.ts frontend/src/lib/print-document.ts frontend/src/lib/admin-presentation.ts frontend/src/lib/session-policy.ts
node scripts/check-policy.cjs "$tmp"
# tsc is installed globally in the authoring environment; also resolve local TypeScript if available.
NODE_PATH="$(npm root -g)${NODE_PATH:+:$NODE_PATH}" node scripts/check-ts-syntax.cjs
