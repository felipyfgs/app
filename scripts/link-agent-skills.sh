#!/usr/bin/env bash
# Regenera symlinks de skills por engine → .agents/skills (canônico).
# Commands em .opencode/commands e .grok/commands são locais (gitignored).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

AGENTS_REL="../../.agents/skills"
SKILLS=(
  panel-ui
  ui-archetype
  task-loop
  openspec-apply-change
  openspec-archive-change
  openspec-explore
  openspec-propose
  openspec-sync-specs
)

link_skills() {
  local dest="$1"
  mkdir -p "$dest"
  for s in "${SKILLS[@]}"; do
    rm -rf "$dest/$s"
    ln -sfn "$AGENTS_REL/$s" "$dest/$s"
  done
  echo "  skills → $dest"
}

echo "Linking agent skills from .agents/skills …"
link_skills .opencode/skills
link_skills .codex/skills
link_skills .grok/skills
echo "OK"
