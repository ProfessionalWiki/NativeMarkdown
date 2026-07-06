---
owner: Platform team
status: current
---

# Platform architecture

How the pieces fit together. The wiki itself runs on this platform, so this page doubles as the
canonical example of our documentation conventions.

[[File:Architecture_Overview.png|640px|alt=Browsers and AI agents connect to MediaWiki, which stores revisions in MariaDB and indexes search in Elasticsearch|How reads, writes and search flow through the platform]]

## Components

| Component | Role | Documentation |
|---|---|---|
| MediaWiki | Serves pages, hosts the editing workflow | [[Markdown Examples.md\|syntax tour]] |
| MariaDB | Stores page revisions and user data | [[Database Operations.md]] |
| Elasticsearch | Powers full-text search | [[Incident Response Runbook.md\|runbook]] |

## Data flow

Browsers get rendered HTML; automated clients fetch the same pages as raw Markdown, which keeps
agent integrations lossless[^raw]. Every saved revision lands in MariaDB and is picked up by the
search indexer within a minute[^jobs].

## Deployments

Changes reach production through the pipeline described in the [[Deployment Guide.md]]:

[[File:Deployment_Pipeline.png|640px|alt=Pull request to CI build to staging to production|The four stages every change passes through]]

## Monitoring

Dashboards and alerts watch the platform around the clock. When an alert fires, follow the
[[Incident Response Runbook.md|runbook]] and roll back the most recent change if it looks suspect.
Runbooks and dashboards are owned by the platform team and reviewed every quarter.

## Related pages

- [[Release Notes 2.4.md]] — what shipped last
- [[:Category:Runbooks]] — operational procedures
- [[:File:Architecture_Overview.png]] — the diagram source file

[^raw]: `action=raw` returns the stored Markdown byte for byte.
[^jobs]: Indexing runs through the job queue, so a stuck queue shows up as stale search results.

[[Category:Documentation]]
[[Category:Architecture]]
[[Category:Monitoring]]
