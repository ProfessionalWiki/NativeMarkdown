# Deployment guide

How we ship. Linked from [[Team Onboarding.md]]; what shipped recently is in [[Release Notes 2.4.md]].

[[File:Deployment_Pipeline.png|640px|alt=Pull request to CI build to staging to production|Every change passes through these four stages]]

## Prerequisites

You need `kubectl` access to the staging cluster and a green CI run on your branch.

## Steps

1. Merge your PR — CI builds and pushes the image.
2. Deploy to staging:

```bash
./deploy.sh staging --tag "$(git rev-parse --short HEAD)"
```

3. Smoke-test staging, then promote:

```bash
./deploy.sh production --promote
```

## Rollback

Same script, previous tag: check the [release dashboard](https://example.org/releases) first, then
`./deploy.sh production --tag <previous>`. If the rollback is part of an incident, follow the
[[Incident Response Runbook.md|incident response runbook]].

[[Category:Markdown examples]]
[[Category:Documentation]]