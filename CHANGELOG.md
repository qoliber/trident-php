# Changelog — qoliber/trident-php

The per-version notes live in README.md (one section per release); this file
lists them in order.

## 1.8.0 — unreleased
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
