# Changelog

All notable changes to the AxiTrace PHP SDK are documented in this file.
This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.8.0]

### Added

- `TransactionEvent::setRecordedAt(\DateTimeInterface)` and the `recorded_at` (or
  `recordedAt`) key of `transaction()` params: the time the order was placed, sent as
  `recordedAt` in UTC. Set it whenever a transaction is sent later than it happened (a
  queue, a cron re-send of failed deliveries, a backfill); without it the order is dated
  at the moment it reaches AxiTrace.
- `transaction()` accepts a `DateTimeInterface` or an ISO 8601 string and throws
  `ValidationException` for anything it cannot read, before sending.
- Guide: https://axitrace.com/docs/troubleshooting/server-side-sending

## [1.7.0]

### Added

- `AxiTrace\Model\Event\CustomEvent` sends a workspace-defined custom event to
  `POST /v1/custom-events`. Define the event key first in the AxiTrace admin panel
  (workspace Settings, Advanced, Custom Events); an undefined key is rejected.
- Constructor: event key, properties, optional value, currency, transaction id and
  event id. When no event id is passed, a UUID v4 is generated, so the request can be
  retried safely: the endpoint deduplicates on `event_id` for 5 minutes.
- `setClientId()` and `setSessionId()` link the event to the visitor (the `vt_vid` and
  `vt_sid` cookie values).
- Guide: https://axitrace.com/docs/events/custom-events

## [1.6.0]

### Added

- `withContext()` accepts a `consent` key carrying the visitor's marketing consent
  state: `granted`, `denied` or `unknown`. The value travels as `params.consent` on
  every event sent afterwards, including `/v1/transaction` and `/v1/page/view`, and
  tells AxiTrace whether the conversion may be forwarded to your ad platforms.
- Any other consent value throws `AxiTrace\Exception\ValidationException`, so a typo
  fails on your side instead of silently disabling the consent signal.

### Notes

- Omitting the `consent` key keeps the previous behaviour: no `params.consent` is sent
  and the server treats the event as having an unknown consent state.
- A purchase from a visitor who refused marketing cookies is still recorded in your
  AxiTrace reports, but it is stripped of ad identifiers and never forwarded to an ad
  platform.
