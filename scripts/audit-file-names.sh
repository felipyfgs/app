#!/usr/bin/env bash
set -euo pipefail

fail=0
warn=0

failure() {
  printf 'FAIL %s\n' "$1"
  fail=1
}

warning() {
  printf 'WARN %s\n' "$1"
  warn=1
}

while IFS= read -r file; do
  base="${file##*/}"

  case "$file" in
    backend/app/*.php|backend/tests/*.php)
      [[ "$base" =~ ^[A-Z][A-Za-z0-9]*\.php$ ]] \
        || failure "$file deve usar PascalCase.php"
      ;;
    backend/config/*.php)
      [[ "$base" =~ ^[a-z0-9]+(_[a-z0-9]+)*\.php$ ]] \
        || failure "$file deve usar snake_case.php"
      ;;
    backend/database/migrations/*.php)
      [[ "$base" =~ ^[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[a-z0-9]+(_[a-z0-9]+)*\.php$ ]] \
        || failure "$file deve usar timestamp_snake_case.php"
      ;;
    frontend/app/components/*.vue)
      [[ "$base" =~ ^[A-Z][A-Za-z0-9]*(\.[A-Z][A-Za-z0-9]*)*\.vue$ ]] \
        || failure "$file deve usar PascalCase.vue"
      ;;
    frontend/app/pages/*.vue)
      if [[ "$base" =~ ^\[.*[A-Z].*\]\.vue$ ]]; then
        warning "$file usa parametro de rota camelCase; padronizar exige ajustar route.params"
      fi
      [[ "$base" =~ ^([a-z0-9]+(-[a-z0-9]+)*|\[[A-Za-z0-9-]+\])\.vue$ ]] \
        || failure "$file deve usar kebab-case.vue ou [param].vue"
      ;;
    frontend/app/composables/api/*.ts)
      [[ "$base" =~ ^create[A-Z][A-Za-z0-9]*Api\.ts$ || "$base" == "types.ts" ]] \
        || warning "$file foge do padrao createXxxApi.ts"
      ;;
    frontend/app/composables/*.ts)
      [[ "$base" =~ ^use[A-Z][A-Za-z0-9]*\.ts$ || "$base" == "types.ts" ]] \
        || failure "$file deve usar useXxx.ts"
      ;;
    frontend/app/utils/*.ts)
      [[ "$base" =~ ^[a-z0-9]+(-[a-z0-9]+)*\.ts$ ]] \
        || failure "$file deve usar kebab-case.ts"
      ;;
    frontend/tests/unit/*.ts)
      [[ "$base" =~ ^[a-z0-9]+(-[a-z0-9]+)*(\.nuxt)?\.test\.ts$ || "$base" == "fiscal-fixtures.ts" || "$base" == "nuxt.config.ts" ]] \
        || warning "$file foge do padrao kebab-case.test.ts"
      ;;
  esac
done < <(git ls-files)

if [[ "$fail" -ne 0 ]]; then
  exit 1
fi

if [[ "$warn" -ne 0 ]]; then
  printf 'OK com avisos: ha nomes que exigem refactor planejado.\n'
else
  printf 'OK: nomes de arquivos seguem os padroes auditados.\n'
fi
