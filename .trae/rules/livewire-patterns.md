---
trigger: model_decision
description: Livewire Component Conventions
---

---
description: Livewire Component Conventions
globs: app/Livewire/**/*.php
alwaysApply: true
---

# Livewire Conventions

- Validation rules live in the component using `protected $rules` or `#[Validate]` attributes — not in separate Form Request classes for Livewire components.
- Use `wire:loading` states for any action that hits the database.
- Component naming: PascalCase matching the Blade view kebab-case (Livewire convention).
- When a component handles a complex domain process (e.g. Military Education enrollment), break logic into a dedicated Service class — keep the Livewire component thin (orchestration only, no business logic inline).
- Always check Spatie permission inside `mount()` or relevant action method, not just by hiding the UI element.