# PRD v2 — GrowthOps P2P Agent parity + GrowthOps MVP agent-platform gaps

**Status:** Draft
**Scope:** Two distinct workstreams, kept separate on purpose:

- **Part A** — close the gap between [growthops-p2p](https://github.com/AIOpsome/growthops-p2p)
  (the decentralized twin, live at https://growthops-p2p.aiopsome.com) and this repository's
  centralized version (live at https://growthops.aiopsome.com/admin). The two are meant to be a
  side-by-side comparison of the same product on two architectures; today the P2P side is a thin
  vertical slice, not a twin.
- **Part B** — gaps in the GrowthOps MVP itself when judged as one agent in AIOpsome's swarm of
  agents (readiness agent, support agent, etc.), independent of the P2P work.

Implementation of Part A lands in the `growthops-p2p` repository; this document lives here because
this repo's implementation is the reference for every parity requirement below.

---

## Part A — growthops-p2p parity with growthops

### A0. Current state (what the P2P side actually has today)

Working, deployed, and verified: Corestore/Autobase event log with deterministic `apply()`,
Hyperbee read model, Hyperswarm invite pairing + two-peer replication, CSV import via CLI, two
detectors (BudgetBleeder, CpaBreach) on an interval, approve/reject via AdminJS custom actions,
audit trail derived from the raw log, two-peer public showcase behind Traefik + basic auth.

Everything below is what's missing relative to this repo.

### A1. Branding — AIOpsome logo, "GrowthOps P2P Agent"

The P2P panel currently shows AdminJS's default logo and the branding string
`growthops-p2p (local peer)`.

**Requirements**
- Replace the AdminJS logo with the AIOpsome logo (same assets this repo uses:
  `public/images/aiopsome-logo-dark.png` / `aiopsome-logo-light.png`, dark/light variants swapped
  by theme, matching `resources/views/filament/partials/brand.blade.php`).
- Brand label: **GrowthOps P2P Agent** (centralized panel is "GrowthOps Agent").
- AdminJS supports this via `branding: { logo, companyName, favicon }` plus theme overrides — no
  custom frontend build should be needed for the logo/name alone.

### A2. Navigation — exactly two menu items

The P2P panel exposes four top-level resources: `campaigns`, `daily_metrics`,
`recommended_actions`, `action_audits`. The centralized panel's working surface is two:
**Campaigns** and **Action Queue**.

**Requirements**
- Sidebar shows exactly two items: **Campaigns** and **Action Queue** (label parity with
  `RecommendedActionResource::$navigationLabel = 'Action Queue'`).
- `daily_metrics` stops being a top-level resource; metrics render inside a campaign's detail view
  (the centralized version aggregates them into campaign-level totals — see A5).
- `action_audits` stops being a top-level resource; the audit trail renders inside an action's
  detail view (centralized: infolist on `ViewRecommendedAction`). The underlying derived-from-log
  read model is unchanged — this is presentation only.
- AdminJS: use `options.navigation = null` (or equivalent) to hide resources from the sidebar
  while keeping their API routes available for embedding in the two remaining views.

### A3. Import CSV from the Campaigns screen

Centralized: `ListCampaigns` has an **Import CSV** header action — file upload (max size from
config, with a helper text warning), optional platform override (`meta`, `google`, `taboola`,
`tiktok`, `leads`, default auto-detect), validation errors surfaced on the form field, success
notification with imported counts. P2P: CLI only (`bin/import-csv.js`), nothing in the UI.

**Requirements**
- "Import CSV" resource-level action on the Campaigns list in AdminJS (custom action with a
  file-upload component), same platform-override select, same auto-detect default.
- Import goes through the existing `lib/csv-import.js` apply path (events, never direct Hyperbee
  writes). Malformed rows reject the whole file with a clear error before any event is appended —
  same contract as `CsvImportException` here.
- Success notification reports campaigns + daily rows imported, matching the centralized wording.
- Platform parser parity is a data requirement, not just UI — see A8.

### A4. "Run daily analysis" from the Action Queue screen

Centralized: `ListRecommendedActions` has a **Run daily analysis** header action — confirmation
modal ("Runs the detector engine against every campaign's current data and rebuilds today's
pending action queue."), runs `growthops:analyze`, shows the command output as a notification.
P2P: detectors run on a background interval only; there is no on-demand trigger in the UI.

**Requirements**
- "Run daily analysis" resource-level action on the Action Queue list, with confirmation, that
  runs all detectors against all campaigns immediately (same code path as the interval loop in
  `bin/serve-peer.js`) and reports how many actions were created.
- The interval loop stays (it is the P2P equivalent of the daily scheduled `growthops:analyze`);
  this adds the manual trigger, it does not replace the loop.
- Note for reviewers: detector runs append events from the *local* peer. Running analysis on peer
  A and peer B concurrently must not double-queue — the existing one-pending-per-campaign-per-kind
  dedup guard already handles this and must be preserved.

### A5. Campaign health classification + column parity

Centralized campaign table: a **Status** badge (`Requires action` / `Healthy` /
`Not analyzed yet`, warning/success/gray with icons), a platform badge, then
**Spend, Impressions, Clicks, Conversions, Revenue, Leads, Accepted, Lead accept %, CPC, CPM,
CPA, CPL, ROAS, EPC** — computed via `withMetricTotals()->withLeadTotals()->withActionStatus()`.
P2P campaign list: `campaignId`, `name`, `platform`. Nothing else.

**Requirements**
- Status badge with the same three states and semantics: `requires_action` = campaign has a
  pending recommended action; `healthy` = analyzed, no pending action; `not analyzed yet` =
  detectors have not run against it. Derive from the Hyperbee view (campaign + pending actions +
  a "last analyzed" marker event or projection field) — never a second writable state store.
- All metric total/derived columns above, computed in the read-model projection (Hyperbee has no
  SQL aggregates — extend `lib/read-model.js` / the `apply()` projection to maintain campaign
  totals as metrics are applied, or compute on read over the range scan; either is acceptable if
  deterministic).
- Column labels, ordering, currency/percent formatting match the centralized table.
- Platform badge rendered as a styled badge (AdminJS custom component), not a raw string.

### A6. Action Queue parity — columns, filters, detail view

Centralized action table: Campaign name, platform badge, **Type** (pause / scale / fix /
investigate, badged), **Confidence**, **Risk**, **Expected upside** ($), **Status**, **Run date**,
with status and type filters. Detail view: evidence panel (the actual metric windows/deltas),
LLM narrative, decision actions, simulated execution payload after approval, full audit trail.
P2P action list: `actionId`, `campaignId`, `kind`, `status`.

**Requirements**
- `action.recommended` events carry the full payload: `type` (pause/scale/fix/investigate),
  `confidence`, `risk`, `expected_upside`, `evidence` (structured, same shape as the centralized
  `evidence` array cast), `run_date`, `narrative` (see A9).
- List columns and filters match the centralized table (campaign name resolved from the campaigns
  projection, not stored redundantly per action).
- Action detail view shows evidence, narrative, audit trail (existing derived-from-log
  `auditTrail`), and — after approval — the simulated execution payload (A7).

### A7. Decision workflow parity — reject-with-reason, edit-then-approve, simulated execution

Centralized: approve / **reject with a required reason** / **edit the parameter then approve**
(e.g. change scale % before approving; stored as `applied_parameter`), every decision writes an
immutable `ActionAudit`, approval builds a **simulated execution payload** (`ExecutionLog`) — the
exact platform API call that would have been sent, never dispatched. P2P: bare approve/reject,
no reason, no parameter editing, no execution payload.

**Requirements**
- `action.rejected` events require and carry a `reason` string (form field in the AdminJS reject
  action).
- Edit-then-approve: the approve action accepts an optional `applied_parameter` override; the
  `action.approved` event carries it.
- On approval, append an `execution.simulated` event carrying the platform-specific payload the
  centralized `SimulatedExecutionBuilder` would produce (port its output shape per platform).
  Rendered on the action detail view, clearly labeled simulated.
- All of this stays on the existing apply path: new event types must be added to `apply()` with
  the same defensive validate-or-skip handling (never throw uncaught — a bad event is already
  durably in the log), and the state machine guards extended accordingly
  (`pending → approved|rejected`, execution only after approval).

### A8. Data-model parity — leads, platform parsers, normalized metrics

Centralized ingests **Meta Ads Manager, Google Ads, Taboola, TikTok Ads Manager, plus a
leads/CRM export** through per-platform parsers into one normalized model (spend, impressions,
clicks, conversions, revenue, plus leads: total/accepted/acceptance rate, and derived CPA, CPL,
ROAS, EPC, CPC, CPM). P2P: one generic CSV shape (`name, platform, external_id, date, spend,
impressions, clicks, conversions, revenue`), no leads at all, and the view/UI only surfaces a
subset even of that.

**Requirements**
- Port the four platform parsers + leads parser (`app/Services/CsvImports/Parsers/*`) to JS with
  their real export-format expectations and auto-detection, including the same test fixtures.
- Add `lead.recorded` (or equivalent) events and a leads projection: totals, accepted, acceptance
  rate per campaign.
- The Meta 72-hour provisional-conversion caveat (BudgetBleeder excludes the trailing window on
  Meta campaigns, drops confidence, surfaces `"caveat": "meta_72h_provisional"` in evidence) is
  part of detector parity — port it, don't approximate it (A10).
- Demo seed parity: the centralized demo seeds 7 campaigns across 4 platforms with 14 days of
  metrics, tuned so exactly three actions fire and three healthy campaigns stay silent. The P2P
  showcase's seed (`reset-demo.sh`) should match that dataset (relative-dated, as it already is)
  so the two live demos are directly comparable screen-for-screen.

### A9. LLM narrative (ActionNarrator)

Centralized: every recommendation gets a plain-English rationale from an OpenAI-compatible
gateway (configurable base URL/key/model/timeout), lazily generated, cached on the row, with a
graceful deterministic template fallback when no LLM is configured. P2P: template-only narrator.

**Requirements**
- Port `ActionNarrator`'s LLM call + prompt, config via env vars, same graceful fallback to the
  existing template narrator.
- **Architecture constraint (hard):** the LLM call must happen *before* `base.append()` — at
  detector/action-creation time on the recommending peer — with the narrative stored in the event
  payload. It must never run inside `apply()`: apply is replayed by every peer and must stay pure
  and deterministic (no network, no clock, no randomness). A peer without LLM config replaying
  the log still sees the narrative because it's data, not derivation.

### A10. Detector parity — four detectors, real thresholds

Centralized runs **BudgetBleeder (pause), ScalingWinner (scale), CpaBreach (fix),
SpendPacingAnomaly (investigate)** through a `DetectorEngine`, each emitting evidence, confidence,
risk, and expected-dollar upside. P2P has BudgetBleeder and CpaBreach only, with reduced payloads.

**Requirements**
- Port `ScalingWinner` and `SpendPacingAnomaly` 1:1 (thresholds, windows, edge cases, and their
  test cases), same as was done for the first two.
- Bring all four up to full payload parity (A6) including the Meta 72h caveat handling (A8).
- Keep them pure functions over the read model that append through the apply path — no
  detector-specific redesign.

### A11. Operator Guide (parity, lower priority)

Centralized has an **Operator Guide** page: an allowlisted-workflow assistant
(find stuck leads / show risky campaigns / prepare weekly report / fill campaign brief — read
or form-assist modes only, never spend-affecting), with invocations logged (`GuideInvocation`).

**Requirement (P2):** port as a third AdminJS page once A1–A10 are done. It is part of the
centralized surface, but the user-facing parity gaps above matter more; note that adding it will
change the two-item navigation to three, matching the centralized panel's evolution.

### A12. Non-negotiable P2P invariants (apply to every item above)

Every Part A item must respect the architecture that makes the P2P side worth demoing:
1. The Hypercore log is the only source of truth; Hyperbee is a derived projection.
2. `apply()` stays pure, deterministic, and never throws uncaught on malformed events.
3. All writes go through the apply path — no side-channel Hyperbee writes (AdminJS `create`/custom
   actions included).
4. `action_audits` remains derived by replaying the raw log — no separate writable audit store.
5. New event types are additive; peers running older code must skip-not-crash on them (the
   defensive `apply()` already guarantees this — keep it that way).

---

## Part B — GrowthOps MVP gaps as an AIOpsome swarm agent

Separate from P2P parity: judged against AIOpsome's direction — a swarm of cooperating agents
(readiness agent, support agent, and peers) rather than standalone tools — the centralized
GrowthOps MVP has its own gaps. These apply to *this* repository.

### B1. No agent identity or swarm surface
GrowthOps brands itself "GrowthOps Agent" but is a standalone Laravel app: no
machine-consumable agent interface (MCP server, A2A endpoint, or even a stable JSON API) that a
sibling agent — e.g. the readiness agent auditing a storefront, or the Opsome support agent —
could call to ask "which campaigns need action today?" or hand work to. The swarm story is
currently branding, not architecture.

### B2. Operator Guide is an allowlist, not an agent
The guide's four hard-coded workflows are honest and safe, but there is no path from "allowlisted
canned workflow" toward the supervised-agent model Opsome itself uses (proposal → human approval →
audited execution). A v2 should either explicitly keep it a static allowlist or converge it with
Opsome's agent-run/approval primitives — currently it's a third pattern maintained separately.

### B3. Scoped-but-missing lead-quality detector
Already disclosed in the README: Taboola/lead-source acceptance-rate collapse detection is scoped,
the data (CPL, acceptance rate, EPC) is computed and displayed, but no detector acts on it. This
is the highest-value pure-detector gap since it's the one closest to real money in the
lead-gen vertical.

### B4. Execution stays simulated with no bridge design
Simulated execution was the right MVP call, but there is no documented design for the trust
boundary when it goes live (per-platform OAuth scopes, budget-change caps, rollback, the approval
gate as the only trigger). Writing that design is cheap now and expensive to retrofit.

### B5. No alerting / digest
The daily queue requires opening the dashboard. A Slack/email digest (already on the README's
next-steps list) is the difference between a tool an operator checks and one that reaches them.

### B6. Single-account only
No multi-account/agency view — one queue across N client accounts is the actual shape of the
buyer-at-scale problem (also already acknowledged in the README's next steps; restated here
because the swarm framing makes it sharper: an agent that can only see one account can't serve
an agency-level swarm deployment).

### B7. No live ingestion
CSV-first was deliberate; the gap is that no ingestion abstraction exists yet that a live
Meta/Google/Taboola/TikTok API connector could drop into. The parsers normalize files, not a
stream — v2 should define the ingestion interface even if connectors come later.

---

## Suggested sequencing

**Part A (growthops-p2p repo):** A1+A2 (branding/nav — small, high-visibility) → A5+A6 (columns,
health, evidence — the "looks like the same product" milestone) → A3+A4 (Import CSV + Run daily
analysis in-UI) → A7 (decision workflow) → A8+A10 (data model + detectors) → A9 (LLM narrative)
→ A11 (Operator Guide, optional).

**Part B (this repo):** B3 (lead-quality detector — pure detector work, no new infra) → B5
(digest) → B1 (agent surface — unlocks the swarm story) → B4 (execution bridge design doc) →
B2, B6, B7 as they earn priority.
