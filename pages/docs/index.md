---
layout: home

hero:
  name: "BlatUI Admin"
  text: "Adaptive Admin Panel & Lifecycle Engine for Laravel"
  tagline: "Built on BlatUI, Tailwind CSS v4, and Alpine.js with zero runtime collisions and full component code ownership."
  actions:
    - theme: brand
      text: Getting Started
      link: /guide/getting-started
    - theme: alt
      text: Architecture & Coexistence
      link: /guide/architecture
    - theme: alt
      text: View on GitHub
      link: https://github.com/x-multibyte/blatui-admin

features:
  - title: Adaptive 4-Tier Installer
    details: Automatically inspects PHP, Laravel, Node package managers (bun, pnpm, yarn, npm), and host packages before executing a deterministic installation plan.
  - title: Zero Duplicate-Alpine
    details: Detects existing Livewire v3/v4, Flux UI, or standalone Alpine.js setups and wires BlatUI onto the host runtime without double-initializing Alpine.
  - title: Additive Tailwind v4 Wiring
    details: Respects existing resources/css/app.css and strict W3C / Lightning CSS @import ordering rules without overwriting application styles.
  - title: Code Ownership Model
    details: Publishes customizable Blade UI components directly into resources/views/components/ui/ so your team owns the markup and styling.
---
