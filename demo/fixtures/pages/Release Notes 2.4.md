---
version: 2.4.0
released: 2026-06-30
owner: Platform team
---

# Platform 2.4 release notes

This release focuses on deployment safety and documentation quality. Upgrade using the
[[Deployment Guide.md|deployment guide]]; breaking changes will be collected in the
[[Migration Guide 2.4.md]] before general rollout.

## Highlights

| Area | Change | Notes |
|---|---|---|
| Deploys | Gradual production rollout | Steps in the [[Deployment Guide.md\|deployment guide]] |
| Search | Cleaner result snippets | Reindex required[^reindex] |
| Docs | Runbooks migrated to Markdown | Overview in [[Platform Architecture.md]] |

## Upgrade checklist

- [x] Announce the maintenance window in `#platform`
- [x] Back up the database
- [ ] Run the search reindex job
- [ ] Confirm the escalation path in the [[Incident Response Runbook.md|incident response runbook]]

## Known issues

- Deploys from forked repositories skip the image cache and build slowly[^cache].
- ~~Staging deploys time out behind the office proxy~~ — fixed in 2.4.1.

## Feedback

File issues in the [platform tracker](https://tracker.example.org/platform) or ask in `#platform`.

[^reindex]: Pages are reindexed on their next edit; run the rebuild job to reindex everything at once.
[^cache]: Workaround: push the branch to the main repository instead of a fork.

[[Category:Documentation]]
[[Category:Release notes]]