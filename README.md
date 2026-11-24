# dog-help-scheduler

[![CI](https://github.com/barlito/dog-help-scheduler/actions/workflows/ci.yaml/badge.svg?branch=master)](https://github.com/barlito/dog-help-scheduler/actions/workflows/ci.yaml)

Random "fake walk" reminders to help a dog with separation anxiety.

My dog panics when the departure routine starts — keys, shoes, door. The therapy is desensitization: perform the routine several times a day *without actually leaving* (fake walks), at unpredictable times, until the cues stop meaning "you're being abandoned". This app plans those fake walks at random times every day, pushes each one to my phone via [ntfy](https://ntfy.sh), and tracks whether they were done — with an EasyAdmin backoffice to tune everything.

## How it works

```
Symfony Scheduler (cron 00:05, Europe/Paris)
        │  PlanDayMessage
        ▼
DayPlanner + RandomScheduleGenerator
  → for each enabled notification type, picks N random times
    inside its daily window, respecting a minimum gap
        │  one delayed SendNotificationMessage per slot (Doctrine transport)
        ▼
Messenger worker → NtfyPublisher (JSON publish)
        │
        ▼
📱 Push notification with 3 action buttons
   ✅ Validé   🔁 Reporter   ❌ Non effectué
        │  HTTP callback  /n/{id}/{token}/{status}
        ▼
Status recorded → dashboard stats + Mercure live refresh
```

Key behaviors, all covered by tests:

- **Random spread with guaranteed gap** — N uniform draws over the window's slack, sorted, then shifted by the minimum gap: always valid, uniformly distributed (`RandomScheduleGenerator`).
- **Idempotent planning** — a type is never planned twice for the same day; the daily trigger, the `app:plan-day` command and the backoffice "plan now" button all share `DayPlanner`.
- **Missed-run catch-up** — the schedule is stateful (checkpoint in a Postgres-backed cache pool), so a trigger missed during downtime fires on restart.
- **Smart postpone** — "🔁 Reporter" re-sends after the type's delay, staggered behind other pending postpones (no burst repop) plus a random jitter.
- **Secure callbacks** — each notification carries a random response token; buttons work without being logged in, but only for that one notification.

## Backoffice

EasyAdmin 5, protected by Discord OAuth restricted to a single whitelisted user ID.

- **Dashboard** — validated/postponed/missed counts per week, month or all-time, auto-refreshed live through Mercure (hub embedded in FrankenPHP).
- **Notification types** — message content, ntfy tags, daily window, count per day, minimum gap, postpone delay + jitter, enable/disable.
- **Notifications** — the day's plan, statuses, cancel (e.g. a day away from home), trigger one immediately.
- **Settings** — ntfy topic, editable without redeploying (falls back to the `NTFY_TOPIC` env var).

## Stack

- **Symfony 8.1** (PHP 8.4) — Scheduler, Messenger (Doctrine transport), Mercure
- **FrankenPHP** — app server + embedded Mercure hub
- **PostgreSQL 18** — data, message queue and scheduler checkpoint
- **EasyAdmin 5** — backoffice
- **ntfy** — push notifications with quick-reply action buttons
- **Docker Swarm + Traefik** — deployment behind [traefik-base](https://github.com/barlito/traefik-base)
- **Make + Castor** — task automation (shared submodule)

## Quick start

Requires Docker (Swarm mode), Make, [Castor](https://castor.jolicode.com/) and [traefik-base](https://github.com/barlito/traefik-base) running.

```bash
git clone --recurse-submodules git@github.com:barlito/dog-help-scheduler.git
cd dog-help-scheduler

# Configure secrets in .env.local: NTFY_TOPIC (treat it as a password),
# DISCORD_CLIENT_ID / DISCORD_CLIENT_SECRET / DISCORD_ALLOWED_USER_ID

make docker.deploy
make composer.install
make doctrine.migrate
```

App runs at `https://doghelp.local.barlito.fr` (php + worker + db services). Create your notification types in the backoffice, then test the pipe end to end:

```bash
make docker.bash
bin/console app:notify:test   # immediate ntfy push with the 3 buttons
bin/console app:plan-day      # plan the rest of today
```

## Commands

```bash
# Stack
make docker.deploy            # Deploy dev stack (php + worker + db)
make docker.bash              # Shell into the PHP container
make deploy.prod              # Prod deploy: backup → migrate → smoke test
make undeploy                 # Remove stack

# Database
make doctrine.migrate         # Run migrations
make doctrine.diff            # Generate migration from entity changes
make db.backup                # pg_dump (custom format) to /srv/doghelp/backups

# Quality
make cs_fixer                 # PHP CS Fixer (fix)
make phpcs                    # PHP CodeSniffer
make phpmd                    # PHP Mess Detector

# Tests
make phpunit                  # PHPUnit test suite
```

## Tests

PHPUnit 12 suites live in `tests/`: the random generator (gap/window guarantees), day planning (idempotency, delayed dispatch), the send handler (cancel, double-delivery guard, failure path), the ntfy payload factory, the response controller (token check, postpone stagger) and the admin dashboard.

```bash
make phpunit
```

## CI/CD

All workflows are thin callers of the reusable workflows of [barlito/ci](https://github.com/barlito/ci) (`@v1`); the project-specific steps live in the Makefile (variables of `make/stack/prod.mk`).

- **[`ci.yaml`](.github/workflows/ci.yaml)** — on pull requests and pushes to `master`: PHP CS Fixer, PHPCS and PHPUnit against a compose-deployed CI stack, plus Docker build and compose validation.
- **[`release.yaml`](.github/workflows/release.yaml)** — on GitHub release: builds and pushes the `barlito/doghelp` and `barlito/doghelp-worker` images to Docker Hub.
- **[`deploy.yaml`](.github/workflows/deploy.yaml)** — manual dispatch, run on a release tag: rolling update, or full `docker stack deploy` with `re-deploy` (never removes the stack). DB backup, migrations, image assertion and a smoke test.
- **[`rollback.yaml`](.github/workflows/rollback.yaml)** — manual dispatch: rolls back to a previous tag, reverting the migrations or restoring the database dump.
- **[`security.yaml`](.github/workflows/security.yaml)** — weekly Trivy scan of the production image.
