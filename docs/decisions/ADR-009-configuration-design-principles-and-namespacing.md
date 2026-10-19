# ADR-009: Configuration Design Principles & Domain Namespacing Standard

## Status
Accepted

## Date
2026-10-19

## Context
During the early architectural phase of `x-multibyte/blatui-admin`, we evaluated the structure and contents of `config/blatui-admin.php`. In package development, there is a recurring temptation to practice "Config-Driven Development"—preemptively authoring elaborate configuration files specifying routes, middleware, authentication guards, and model bindings before any corresponding controllers, views, or business logic exist.

Furthermore, there is a risk of conflating distinct architectural layers:
1. **Application Runtime Configuration** (`config/*.php`): Persistent settings controlling application behavior in production.
2. **Installation & Scaffolding Lifecycle** (`InstallWizard`, CLI flags): One-time setup parameters executed during `blatui:admin --install`.
3. **Upstream Ecosystem Invariants** (`anousss007/blatui`): BlatUI dictates a code-ownership model placing Blade components into `resources/views/components/ui/` (`<x-ui.*>`). Providing configuration options to alter this path introduces a leaky abstraction that breaks upstream compatibility.

Without clear architectural rules, development risks devolving into guesswork, premature abstraction, and "ghost configuration" (configuration parameters that do not actively drive any code).

## Decision
Establish a strict set of configuration governance principles and an incremental domain namespacing standard:

### 1. The Anti-Config-Driven Rule (No Ghost Configurations)
- **Zero Preemptive Parameters**: Configuration keys MUST NEVER be introduced for features that are not yet implemented in code.
- **Active Consumption Mandate**: A configuration entry is permitted in `config/blatui-admin.php` if and only if there is verified, actively executing code consuming it via `config('blatui-admin.<domain>.<key>')`.
- **Code Precedes Configuration**: Development flows from feature implementation to configuration extraction (as tuning knobs for legitimate host customisation), never from configuration wishlists back to code.

### 2. Separation of Runtime Configuration and Installer Parameters
- Do not store one-time installer scaffolding choices (e.g. interactive multi-select component lists, `--install-apex-charts`, `--force`) in `config/blatui-admin.php`.
- Installer parameters belong exclusively to CLI options and the transient `InstallContext` pipeline.

### 3. Non-Interference with Upstream Ecosystem Standards
- Do not introduce configuration overrides for conventions enforced by the upstream `anousss007/blatui` package (such as component directories or `<x-ui.*>` tag structures).
- Honor BlatUI's code ownership model without adding redundant configuration wrappers.

### 4. Standard Domain Namespacing Hierarchy
When subsequent features are developed and legitimately require host customization, configuration MUST strictly adhere to domain-isolated namespaces:
- **`blatui-admin.routes`**: Introduced only when administrative dashboard routing and controllers are implemented (e.g. `enabled`, `prefix`, `middleware`, `domain`).
- **`blatui-admin.auth`**: Introduced only when authentication guards, providers, and session mechanisms are implemented (e.g. `guard`, `middleware`).
- **`blatui-admin.models`**: Introduced only when customizable domain models or internal entities are implemented.

### 5. Current Baseline
- In the current development stage, `blatui-admin` serves as an installer and dependency lifecycle management engine.
- Neither administrative routes, auth guards, nor entity models currently exist in `src/`.
- Therefore, `config/blatui-admin.php` MUST NOT add speculative configurations. The legacy boilerplate `'placeholder' => 'default'` will be phased out, leaving a clean, documented baseline that expands only as tangible features land.

## Alternatives Considered

### 1. Pre-populating a "Full-Featured" Configuration Skeleton
- **Pros**: Gives a preview of what a completed admin panel might support in the future.
- **Cons**: Creates confusing "ghost configurations" where developers change values that have zero effect on the system; encourages premature and brittle code design.
- **Verdict**: Rejected.

### 2. Exposing Component Paths and Default Component Lists in Config
- **Pros**: Allows theoretical customization of component storage directories.
- **Cons**: Directly breaks compatibility with upstream `php artisan blatui:add` and standard Blade component tagging (`<x-ui.*>`); confuses installation defaults with runtime config.
- **Verdict**: Rejected.

## Consequences
- **Positive**:
  - Eliminates guesswork and ensures 100% of published configurations correspond to real, testable code behaviors.
  - Keeps `config/blatui-admin.php` minimal, maintainable, and aligned with YAGNI principles.
  - Establishes a predictable, standardized expansion pattern (`routes`, `auth`, `models`) for future milestones.
- **Negative**:
  - `config/blatui-admin.php` remains empty or minimal in early releases, requiring disciplined documentation to guide developers on roadmap milestones.
