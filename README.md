# Opsome GrowthOps

A supervised AI media-buyer control plane. Upload the CSV exports your team already has from Meta, Google, Taboola, and TikTok, and it turns them into a single daily action queue — pause, scale, investigate, fix — each with the evidence behind it, a confidence score, a risk level, and an expected-dollar upside. Nothing ever touches an ad account without a human clicking approve first.

**Live demo:** https://growthops.aiopsome.com/admin
**Login:** `demo@growthops.test` / `growthops-demo`

Built in ~36 hours for It's Today Media's Build Challenge by [Waqas Ahmed](https://github.com/wakqasahmed), founder of [Opsome](https://github.com/AIOpsome/opsome) — an agentic-commerce ops platform this demo borrows its core architecture from.

---

## 1. What it does

Media buyers already live in CSV exports pulled from four different dashboards. The hard part isn't the data — it's turning "here's what happened" into "here's what to do about it, today" without babysitting every campaign by hand.

GrowthOps ingests those exports (Meta Ads Manager, Google Ads, Taboola, TikTok Ads Manager, plus a leads/CRM export) into one normalized performance model — spend, clicks, conversions, revenue, lead acceptance rate, CPA, CPL, ROAS, EPC — then runs a detector pass over it every day:

| Detector | Fires on | Action |
|---|---|---|
| Budget bleeder | Sustained spend, conversions collapsed vs. the campaign's own baseline | **Pause** |
| Scaling winner | ROAS above target, stable or climbing | **Scale** |
| CPA breach | Cost per result consistently over target | **Fix** |
| Spend pacing anomaly | Day-over-day spend spike or collapse | **Investigate** |

Every recommendation carries its evidence (the actual metric windows and deltas that triggered it), a confidence score, a risk level, and an expected-dollar upside — plus a plain-English rationale generated from that same evidence. A human reviews the queue and can **approve**, **reject with a reason**, or **edit the parameter and then approve** (e.g. change the scale percentage before it goes through). Every decision is written to an immutable audit trail, and approval produces a **simulated** execution payload — the exact API call that *would* go to Meta/Google/Taboola/TikTok — so you can see precisely what the system would have done, with zero risk to a real ad account.

## 2. Why this tool

The gap in affiliate media buying isn't reporting — every platform gives you a dashboard. The gap is the hour between "I have five tabs of numbers open" and "I know what to change before more budget leaks." A campaign bleeding $200/day for three days before someone notices is $600 gone for nothing; multiply that across dozens of campaigns and the annual leak is the real cost center, not any individual mistake.

Two ways to solve this both fail buyers in practice:

- **A better dashboard** (Looker, native reporting) still requires a human to notice the pattern and decide. It doesn't reduce the decision, just the friction of seeing it.
- **Full autopilot** (auto-rules, "AI media buyer" platforms) removes the human at exactly the moment trust matters most. Buyers who've had auto-rules torch a budget on bad data don't want a system that acts — they want a system that *recommends and waits*.

GrowthOps is built as the second thing on purpose: evidence and confidence, never autonomy. That's also a direct extension of what I'm already building at [Opsome](https://github.com/AIOpsome/opsome) — a supervised AI ops platform for ecommerce support, where the same principle (AI proposes, human approves, everything is audited) is the whole product thesis. Media buying turned out to be the same shape of problem wearing different data.

One detail that's easy to get wrong and expensive to get wrong: **Meta restates conversion counts for up to 72 hours** after they're reported. A naive detector reading raw daily conversions will see the most recent 1–3 days look artificially weak and recommend pausing a perfectly healthy campaign. GrowthOps' budget-bleeder detector explicitly excludes that trailing provisional window from its judgment on Meta campaigns, drops confidence when a signal would otherwise depend on it, and surfaces the caveat directly in the evidence payload (`"caveat": "meta_72h_provisional"`) rather than hiding it. It's a small thing, but it's the difference between a demo and a tool a real media buyer would trust with their queue.

## 3. What I'd build next, full-time

This is a CSV-first proof by design — no ad-platform OAuth, no live writes, on purpose, because getting the decision layer right matters more than plumbing in week one. The natural next steps:

- **Live ingestion**: OAuth into Meta Marketing API, Google Ads API, Taboola Backstage, TikTok Marketing API — same normalized model, no more manual exports.
- **Real execution behind the same approval gate**: the simulated execution payloads already built here become real API calls the moment a human clicks approve — the trust model doesn't change, only the wire gets connected.
- **Alerting**: Slack/email digest of the daily queue instead of requiring someone to open the dashboard.
- **Lead-quality feedback loop**: close the loop with the CRM so accepted/rejected lead outcomes retrain what "quality" means per campaign, not just a static threshold.
- **Multi-account / agency view**: one queue across every client account, because the actual buyer at scale isn't managing one campaign, they're managing forty.

## What's real vs. simulated (read this before judging)

- **Real**: CSV parsing against actual Meta/Google/Taboola/TikTok export formats, the normalized data model, all four detectors running on real thresholds, the full approve/reject/edit workflow, the audit trail, the LLM narrative generation (uses a configurable OpenAI-compatible gateway).
- **Simulated, clearly labeled in the UI**: the "execution" step. Approving an action constructs and stores the exact API payload that would be sent — it is never sent. This is a design choice, not a limitation: shipping to a stranger's contest with live write access to nothing was the correct call for both parties.
- **Known gap, honestly disclosed**: a lead-quality-collapse detector (Taboola/lead-source acceptance-rate trend) is scoped but not yet implemented — the underlying leads data and metrics (CPL, acceptance rate, EPC) are fully computed and visible in the campaign table, just not yet acted on by a detector.

## Architecture

```
CSV upload (Meta / Google / Taboola / TikTok / Leads)
        │
        ▼
  Platform parsers  ──▶  campaigns + daily_metrics + leads (normalized, upserted)
        │
        ▼
  growthops:analyze (daily, idempotent)
        │
        ▼
  Detectors (BudgetBleeder / ScalingWinner / CpaBreach / SpendPacingAnomaly)
        │
        ▼
  recommended_actions  ──▶  LLM narrative (lazy, cached, graceful fallback)
        │
        ▼
  Filament approval queue ──▶ approve / reject / edit
        │                           │
        │                           ▼
        │                    action_audits (immutable)
        ▼
  execution_logs (SIMULATED payload — never dispatched)
```

Laravel 13 + Filament v5.6, SQLite, deployed via Docker + Traefik with Let's Encrypt TLS.

## 5-minute guided demo

1. **Log in** at https://growthops.aiopsome.com/admin with `demo@growthops.test` / `growthops-demo`. You'll land on the campaign table — seven campaigns across Meta, Google, Taboola, and TikTok, 14 days of real normalized metrics each.
2. **Open the action queue** (Recommended Actions in the sidebar). Three pending recommendations:
   - `Meta Prospecting - Winter Sale` → **Pause**, confidence 0.70, upside ≈ $3,500 — a budget bleeder.
   - `Google Search - Branded Terms` → **Scale**, confidence 0.80, upside ≈ $1,890 — a ROAS winner.
   - `TikTok Spark Ads - UGC Creators` → **Fix**, confidence 0.80, upside ≈ $598 — a CPA breach.
   Notice what's *not* there: `Meta Retargeting - Cart Abandoners`, `Google Performance Max`, and `TikTok Awareness` produced **zero** actions — the detectors are silent on healthy data, not just eager to flag things.
3. **Open the Meta Prospecting action.** Read the evidence panel — the actual spend/conversion numbers behind the pause call — and the narrative underneath it, generated from that same evidence. This is also where the Meta 72h-provisional caveat would surface on a campaign it applies to.
4. **Approve one, reject another with a reason, edit-then-approve the third** (e.g. change the scale percentage on the Google campaign before approving). Reopen each action afterward: you'll see the simulated execution payload (the exact API call it would have sent) and the full audit trail — who decided what, when, and why.
5. **Restart-safe by design**: every decision you just made is written to an immutable audit table and survives a container restart — this isn't a toy in-memory demo.

## Local setup

```bash
git clone https://github.com/AIOpsome/growthops
cd growthops
docker compose up --build
```

The container runs migrations and seeds the demo dataset on first boot only (re-running `docker compose up` won't wipe your decisions). Visit `http://localhost:8000/admin`, log in with the demo credentials above.

To regenerate the daily action queue manually: `docker compose exec app php artisan growthops:analyze`.

Sample CSVs used to build and test the parsers live in [`tests/fixtures/`](tests/fixtures/) — a good starting point if you want to upload your own campaign data instead of the seeded demo.

## Tests

```bash
docker compose exec app php artisan test
```

49 tests covering CSV parsing/upsert idempotency for all four platforms, lead ingestion and revenue rollups, all four detectors (fire + stay-silent cases), the Meta 72h provisional-window handling, the full approve/reject/edit state machine including the server-side pending-precondition guard, and the LLM narrative service with graceful fallback (mocked HTTP in CI — no live API calls).
