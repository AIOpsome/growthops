# Rehearsal fixture

`live-upload-demo-google-ads.csv` — a synthetic 14-day Google Ads export (real column format, authentic 2-line preamble) built specifically for the live-upload contest walkthrough. Two campaigns: a budget bleeder that should trigger a `pause` recommendation after `growthops:analyze`, and a healthy campaign that should correctly produce zero actions.

Use `/internal/demo-reset/{token}` between rehearsal takes to reset the app back to its canonical 7-campaign state before re-uploading this file, so the walkthrough always starts from the same known state.
