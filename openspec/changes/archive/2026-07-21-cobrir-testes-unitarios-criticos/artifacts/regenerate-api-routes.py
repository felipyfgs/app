#!/usr/bin/env python3
"""Regenerate surface-inventory api-routes.json from artisan route:list --json."""

from __future__ import annotations

import json
import sys
from collections import Counter
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[4]
LIVE_ROUTES_DEFAULT = Path("/tmp/routes-live.json")
INVENTORY_PATHS = [
    REPO_ROOT / "apps/api/tests/fixtures/surface-inventory/api-routes.json",
    REPO_ROOT
    / "openspec/changes/cobrir-testes-unitarios-criticos/artifacts/api-routes.json",
]
SUMMARY_PATHS = [
    REPO_ROOT / "apps/api/tests/fixtures/surface-inventory/summary.json",
    REPO_ROOT / "apps/web/tests/fixtures/surface-inventory/summary.json",
    REPO_ROOT
    / "openspec/changes/cobrir-testes-unitarios-criticos/artifacts/summary.json",
]


def normalize_method(method: str) -> str:
    if method == "GET|HEAD":
        return "GET"
    if "|" in method:
        return method.split("|", 1)[0]
    return method


def shorten_action(action: str) -> str:
    if not action or action.startswith("Closure"):
        return "Closure"
    if "@" in action:
        cls, method = action.rsplit("@", 1)
        return f"{cls.split(chr(92))[-1]}@{method}"
    return action.split(chr(92))[-1]


def build_prefix_group_map(existing: list[dict]) -> dict[str, set[str]]:
    prefix_groups: dict[str, set[str]] = {}
    for row in existing:
        uri = row["uri"]
        group = row["group"]
        prefix_groups.setdefault(uri, set()).add(group)
        parts = uri.split("/")
        for i in range(1, len(parts) + 1):
            prefix = "/".join(parts[:i])
            prefix_groups.setdefault(prefix, set()).add(group)
    return prefix_groups


def group_for_uri(uri: str, exact: dict[str, str], prefix_groups: dict[str, set[str]]) -> str:
    if uri in exact:
        return exact[uri]

    parts = uri.split("/")
    best_group: str | None = None
    best_len = -1
    for i in range(len(parts), 0, -1):
        prefix = "/".join(parts[:i])
        groups = prefix_groups.get(prefix, set())
        if len(groups) == 1:
            group = next(iter(groups))
            if i > best_len:
                best_len = i
                best_group = group
    if best_group:
        return best_group

    if uri.startswith("api/v1/auth/") or uri in ("api/v1/account", "api/v1/me"):
        return "auth"
    for prefix in (
        "login",
        "logout",
        "forgot-password",
        "reset-password",
        "sanctum/",
        "user/",
    ):
        if uri.startswith(prefix):
            return "auth"
    if uri.startswith("api/v1/"):
        return uri.split("/")[2]
    if uri.startswith("horizon"):
        return "monitoring"
    if uri.startswith("storage/"):
        return "storage"
    if uri == "up":
        return "monitoring"
    raise ValueError(f"Unable to infer group for URI: {uri}")


def load_json(path: Path) -> object:
    with path.open(encoding="utf-8") as handle:
        return json.load(handle)


def write_json(path: Path, payload: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8") as handle:
        json.dump(payload, handle, indent=2, ensure_ascii=False)
        handle.write("\n")


def merge_api_by_group(
    previous: dict[str, int], counts: Counter[str]
) -> dict[str, int]:
    merged: dict[str, int] = {}
    for key in previous:
        if key in counts:
            merged[key] = counts[key]
    for key, value in sorted(counts.items()):
        if key not in merged:
            merged[key] = value
    return merged


def update_summary(summary_path: Path, inventory: list[dict]) -> None:
    summary = load_json(summary_path)
    if not isinstance(summary, dict):
        raise TypeError(f"Expected object in {summary_path}")

    by_method = Counter(row["method"] for row in inventory)
    by_group = Counter(row["group"] for row in inventory)
    previous_group_order = summary.get("apiByGroup", {})

    summary["apiTotal"] = len(inventory)
    summary["apiByMethod"] = {method: by_method[method] for method in sorted(by_method)}
    summary["apiByGroup"] = merge_api_by_group(previous_group_order, by_group)

    write_json(summary_path, summary)


def diff_inventory(
    previous: list[dict], current: list[dict]
) -> tuple[list[tuple[str, str]], list[tuple[str, str]]]:
    prev_keys = {(row["method"], row["uri"]) for row in previous}
    curr_keys = {(row["method"], row["uri"]) for row in current}
    added = sorted(curr_keys - prev_keys)
    removed = sorted(prev_keys - curr_keys)
    return added, removed


def main() -> int:
    live_path = Path(sys.argv[1]) if len(sys.argv) > 1 else LIVE_ROUTES_DEFAULT
    if not live_path.is_file():
        print(f"Live routes file not found: {live_path}", file=sys.stderr)
        return 1

    live_rows = load_json(live_path)
    if not isinstance(live_rows, list):
        print("Live routes JSON must be an array", file=sys.stderr)
        return 1

    seed_path = INVENTORY_PATHS[0]
    previous = load_json(seed_path)
    if not isinstance(previous, list):
        print("Existing inventory must be an array", file=sys.stderr)
        return 1

    exact_groups = {row["uri"]: row["group"] for row in previous}
    prefix_groups = build_prefix_group_map(previous)

    inventory: list[dict] = []
    for row in live_rows:
        if not isinstance(row, dict):
            continue
        uri = str(row.get("uri", ""))
        method = normalize_method(str(row.get("method", "")))
        action = shorten_action(str(row.get("action", "")))
        group = group_for_uri(uri, exact_groups, prefix_groups)
        inventory.append(
            {"method": method, "uri": uri, "group": group, "action": action}
        )

    added, removed = diff_inventory(previous, inventory)

    for path in INVENTORY_PATHS:
        write_json(path, inventory)

    for path in SUMMARY_PATHS:
        update_summary(path, inventory)

    live_count = len(live_rows)
    inventory_count = len(inventory)
    print(f"live route:list count: {live_count}")
    print(f"inventory count:       {inventory_count}")
    print(f"match: {live_count == inventory_count}")

    print(f"\nadded ({len(added)}):")
    for method, uri in added:
        row = next(r for r in inventory if r["method"] == method and r["uri"] == uri)
        print(f"  + {method} {uri} [{row['group']}] {row['action']}")

    print(f"\nremoved ({len(removed)}):")
    for method, uri in removed:
        row = next(r for r in previous if r["method"] == method and r["uri"] == uri)
        print(f"  - {method} {uri} [{row['group']}] {row['action']}")

    by_method = Counter(r["method"] for r in inventory)
    by_group = Counter(r["group"] for r in inventory)
    print("\napiByMethod:", dict(sorted(by_method.items())))
    print("apiByGroup (top):", by_group.most_common(8))

    return 0 if live_count == inventory_count else 2


if __name__ == "__main__":
    raise SystemExit(main())
