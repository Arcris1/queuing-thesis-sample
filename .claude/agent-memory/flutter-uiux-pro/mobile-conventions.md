---
name: mobile-conventions
description: Smart Queue Flutter app — theming tokens, Riverpod patterns, feature/folder layout, and the in-memory-fake testing style to match.
metadata:
  type: project
---

Conventions the `mobile/` student app follows (match these, don't reinvent):

- **Theme/tokens** live in `lib/theme/app_theme.dart`: `AppTheme.light()/dark()`
  seeded from one brand blue (`0xFF2563EB`), `useMaterial3`, Inter via
  `google_fonts`. Spacing scale is `AppSpacing.xs/sm/md/lg/xl/xxl`
  (4/8/12/16/24/32). Never hard-code spacing or colours — pull from these. Cards
  are elevation 0 on `surfaceContainerLow`, radius 16; filled buttons are 52px
  tall, radius 14.
- **State** is Riverpod `Notifier`/`NotifierProvider` with immutable state classes
  (`copyWith`, `clearX` bool flags). Repositories are plain classes injected via a
  `Provider` that is **overridden in tests** (e.g. `queueRepositoryProvider`).
  `apiClientProvider`/`tokenStorageProvider` live in `features/auth/auth_controller.dart`.
- **API**: `lib/data/api_client.dart` (Dio) unwraps the Laravel `{ "data": ... }`
  envelope and maps errors to `ApiException(message, statusCode)`. Config/base URL
  in `lib/core/config.dart` (dart-define overridable; default Android emulator
  `10.0.2.2`).
- **Feature layout**: `lib/features/<feature>/` holds model + repository +
  controller + state + screens, with shared widgets under `widgets/`. The presence
  loop (`features/presence/heartbeat_controller.dart`) is lifecycle-tied to
  `hasActiveTicketProvider`/`activeTicketProvider` (`features/queue/`), which derive
  "active" from a ticket's non-terminal lifecycle.
- **Ticket lifecycle**: `features/queue/ticket_status.dart` `TicketStatus` enum
  (waiting/called/serving/served/skipped/standby/closed) with `isActive`/`isTerminal`
  is the canonical classifier — branch on it, not raw status strings.
- **Tests** (`test/`) use in-memory fakes that `implements` the repo/service
  interfaces and `ProviderScope`/`ProviderContainer` overrides — mirror the
  existing fakes when adding tests. Offline presence stubs are needed whenever a
  screen mounts the heartbeat loop. For screens with looping animations, use
  `pump(Duration)` not `pumpAndSettle` (it never settles); long scroll views need
  `scrollUntilVisible` before finding below-the-fold widgets.
- **Accessibility** is enforced: `Semantics` labels on icon-only/interactive bits,
  `liveRegion: true` on status banners, meaning never carried by colour alone
  (icon + copy), AA contrast via tonal scheme containers.
