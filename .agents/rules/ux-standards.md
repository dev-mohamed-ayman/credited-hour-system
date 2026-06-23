---
trigger: model_decision
description: UI/UX Standards - Toast, SweetAlert2, Deletion Guard
---

---
description: UI/UX Standards - Toast, SweetAlert2, Deletion Guard
globs: resources/views/**/*.blade.php, resources/js/**/*.js
alwaysApply: true
---

# UX Standards

- Success/error feedback MUST use Toast notifications, never plain Blade session flash messages alone.
- Destructive actions (delete, cancel enrollment, etc.) MUST use the reusable Deletion Guard component — never a plain `<a>` link with onclick confirm().
- All confirmation dialogs use SweetAlert2. Never use native `confirm()` or `alert()`.
- Livewire components: use `wire:confirm` only if it triggers SweetAlert2 under the hood; otherwise dispatch a browser event that opens the SweetAlert2 modal.
- Keep JS for Toast/SweetAlert2 centralized — do not inline duplicate SweetAlert2 config in every Blade file.