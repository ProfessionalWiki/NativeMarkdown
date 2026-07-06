---
owner: Platform team
status: stub
---

# Database operations

How we run MariaDB for the platform: backups, migrations and restores. This page sits behind the
[[Platform Architecture.md|architecture overview]]; during an incident, work from the
[[Incident Response Runbook.md|runbook]] instead.

## Backups

Nightly logical dumps plus continuous binlog shipping to object storage. Restores are rehearsed
once a quarter against the staging cluster.

## Migrations

Schema changes ship through the same [[Deployment Guide.md|deployment pipeline]] as code, guarded by
an online schema-change tool so large tables migrate without downtime.

[[Category:Documentation]]
[[Category:Runbooks]]
