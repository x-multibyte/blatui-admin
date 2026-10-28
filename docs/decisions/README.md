# Architecture Decision Records (ADRs)

This directory documents the key architectural and engineering decisions made in `x-multibyte/blatui-admin`. Each record captures the problem context, technical constraints, rejected alternatives, and long-term architectural consequences.

## ADR Index

| Number | Title | Status | Date | Area |
| :--- | :--- | :--- | :--- | :--- |
| [ADR-001](ADR-001-four-tier-installer-lifecycle-pipeline.md) | Four-Tier Installer Lifecycle Pipeline | Accepted | 2026-10-18 | Architecture & Testability |
| [ADR-002](ADR-002-alpine-runtime-collision-avoidance-strategy.md) | Alpine.js Runtime Collision Avoidance Strategy | Accepted | 2026-10-18 | Frontend Runtime & Livewire/Flux |
| [ADR-003](ADR-003-additive-w3c-compliant-css-wiring.md) | Additive, W3C/Lightning CSS-Compliant Wiring | Accepted | 2026-10-18 | Stylesheets & Tailwind CSS v4+ |
| [ADR-004](ADR-004-single-channel-console-ui-with-laravel-prompts.md) | Single-Channel Console UI Standard (`laravel/prompts`) | Accepted | 2026-10-18 | CLI Experience & Headless Support |
| [ADR-005](ADR-005-command-namespace-migration-and-compatibility-proxy.md) | Command Namespace Migration and Compatibility Proxy | Accepted | 2026-10-18 | CLI Namespaces & Backwards Compatibility |
| [ADR-006](ADR-006-code-ownership-model-for-ui-components.md) | Code Ownership Model for UI Components (`blatui:add`) | Accepted | 2026-10-18 | Component Distribution & Customization |
| [ADR-007](ADR-007-heavy-dependency-isolation-opt-in-apexcharts.md) | Heavy Dependency Isolation (Opt-in ApexCharts) | Accepted | 2026-10-18 | Bundle Footprint & Optional Dependencies |
| [ADR-008](ADR-008-composer-peer-dependencies-decoupling.md) | Composer Peer Dependencies Decoupling & Adaptive Installation | Accepted | 2026-10-18 | Package Dependencies & Laravel 13 Support |
| [ADR-009](ADR-009-configuration-design-principles-and-namespacing.md) | Configuration Design Principles & Domain Namespacing Standard | Accepted | 2026-10-19 | Configuration & Package Architecture |
| [ADR-010](ADR-010-laravel-boost-ai-context-integration-and-distribution.md) | Laravel Boost AI Context Integration & Distribution Strategy | Accepted | 2026-10-22 | AI Context & Package Distribution |
| [ADR-011](ADR-011-direct-blatui-dependency-in-host-application.md) | Direct BlatUI Host Dependency Installation on `admin:install` | Accepted | 2026-10-29 | Package Dependencies & Installer Mandate |

## ADR Lifecycle

- **PROPOSED**: Under discussion or undergoing proof-of-concept validation.
- **ACCEPTED**: Approved and implemented in the codebase.
- **SUPERSEDED**: Replaced by a newer ADR (prior record remains for historical context; never physically deleted).
- **DEPRECATED**: Retired without a direct successor.
