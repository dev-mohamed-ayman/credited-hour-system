---
alwaysApply: false
description: Permissions System Rules
---
---
description: Spatie Permissions System Rules
globs: app/**/*.php, routes/**/*.php
alwaysApply: true
---

# Permissions System

- This project uses Spatie laravel-permission with DIRECT permissions ONLY.
- NO roles are used. Never suggest creating or assigning roles.
- Super Admin user bypasses ALL permission checks via `Gate::before()` in AuthServiceProvider.
- Every controller action that modifies data MUST check permission first using:
  - `$this->authorize('permission.name')` in controllers, OR
  - `@can('permission.name')` in Blade views
- Permission naming convention: `{module}.{action}`
  Examples: `students.create`, `students.delete`, `enrollment.approve`
- When creating a new feature, ALWAYS ask which permission name to use if not specified, and add it to the permissions seeder.
- Never hardcode role checks like `if ($user->role === 'admin')` — always use permission checks.