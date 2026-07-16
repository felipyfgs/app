#!/usr/bin/env bash
# Garante symlinks de descoberta → .opencode/skills (fonte real).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
test -d .opencode/skills || { echo "faltando .opencode/skills" >&2; exit 1; }
mkdir -p .agents .grok
ln -sfn ../.opencode/skills .agents/skills
ln -sfn ../.opencode/skills .grok/skills
echo "OK .agents/skills e .grok/skills → .opencode/skills"
