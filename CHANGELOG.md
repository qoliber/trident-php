# Changelog — qoliber/trident-php

The per-version notes live in README.md (one section per release); this file
lists them in order.

## 1.8.2 — unreleased
- Fix: native DNS validation shares finite request/connect deadlines, with the
  remaining cURL budgets debited after resolution. Preserve native A/NSS/AAAA
  lookup, all-address metadata refusal and checked-address pinning.
- Deployment: bounded hostname requests require a matching trusted PHP CLI,
  enabled process functions and qualified nonblocking pipes. Missing capability
  fails explicitly; no unbounded fallback is used. Numeric hosts, explicit zero
  options and caller-configured PSR-18 clients retain their existing behavior.
  See the README's guarded transport deadline requirements and upgrade guide.
- Fix: Launch start preserves explicit owned URLs and `auto_complete: false`
  through the shared admin service, manager and HTTP client. The shared admin
  service refuses missing, malformed and foreign URLs before engine transport.
- Current integrations require the corrected `~1.8.2` SDK boundary. This is an
  unreleased package candidate, not an existing public tag; local `1.8.99` path
  aliases are development fixtures. Publish the corrected package before
  distributing integrations that require it.

## 1.8.1
- New: `Compatibility` — the Trident release line this library targets
  (`Compatibility::TRIDENT = '1.8'`) and `Compatibility::warning()`, which the shared
  `Admin\AdminService` dashboard now shows as a "compatibility" row when a connected
  engine runs another MAJOR.MINOR. Packages are versioned in lockstep with the engine
  (MAJOR.MINOR = Trident's, PATCH per package).

## 1.8.0
- New: `Delivery\OutboxDelivery`, `Config\Settings`, `Config\SettingsResolver`,
  `Tags\TagPolicy` (moved from qoliber/trident-symfony so every platform shares them).
- Fix: `TagPolicy::purgeTags()` ignores blank tags (they became the bare prefix).
- New: `Delivery\NotBeforeStore` — rows that must not be delivered before a moment (a
  scheduled price's start/end); `Drainer::drainAll()` ("deliver now") holds them back and
  still delivers every early-deliverable row. `InMemoryOutboxStore` implements it.

## 1.7.0
- `Http\NetworkGuard`, `Http\TransportFactory`, `Security\TokenVault`,
  `Delivery\ApiHostAllowlist`, the shared `Admin\AdminService`, `Drainer::drainAll()`.

## 1.6.0, 1.5.0, 1.4.x
- See README.md.
