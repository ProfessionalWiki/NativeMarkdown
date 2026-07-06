---
owner: Platform team
review-by: 2026-12-01
---

# Incident response runbook

What to do when production misbehaves. Keep this page open during an incident; if you are new,
read [[Team Onboarding.md]] first. Current platform status: https://status.example.org

## Severity levels

| Level | Meaning | Response time |
|---|---|---|
| SEV-1 | Customer-facing outage | 15 minutes, page the on-call |
| SEV-2 | Degraded service, workaround exists | 4 hours |
| SEV-3 | Cosmetic or internal only | Next business day |

When in doubt between two levels, pick the higher one and downgrade later (see
[[#After the incident]]).

## First response

- [ ] Acknowledge the page in the alerting tool
- [ ] Open an incident channel and post the dashboard link
- [ ] Check the last deploy: was it in the past hour?

If the last deploy looks suspicious, roll it back:

```bash
./deploy.sh production --tag <previous>
```

Rollback details live in the [[Deployment Guide.md#Rollback|deployment guide]].

## Escalation

Escalate a SEV-1 that is not improving after 30 minutes to the engineering manager on call[^oncall].
Database incidents additionally page the data team.

## Communication

Post updates to the status page every 30 minutes during a SEV-1, even when nothing changed.
Blameless language only — we practice
[[wikipedia:Postmortem documentation|blameless postmortems]].

## After the incident

- [ ] Downgrade or close the incident in the alerting tool
- [ ] Schedule the postmortem within two business days using the [[Postmortem Template.md]]
- [ ] File follow-up tickets and link them from the postmortem

[^oncall]: The rotation is in the calendar; the schedule owner is listed in [[Team Onboarding.md]].

[[Category:Documentation]]
[[Category:Runbooks]]