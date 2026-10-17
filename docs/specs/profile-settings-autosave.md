# Spec: Profile settings autosave with coloured, fading toasts

**Status:** Done · **Date:** 2026-10-18 · **Owner:** aungphyohtet

---

## 1. Context
The profile settings page (`/profiles/{profile}`, `resources/views/pages/⚡profile-settings.blade.php`) saves every field at once with a Save button and shows one green toast (`resources/views/components/toast.blade.php`, rendered once in `resources/views/layouts/app.blade.php`).

The user wants:
- no Save button; each field saves on its own, like the meeting notes page (`resources/views/pages/⚡meeting-notes.blade.php`),
- toasts in different colours for different outcomes,
- toasts that fade in, then fade out as they disappear.

Visible field labels stay.

## 2. Requirements (EARS)

**R1 Autosave**
- R1.1 WHEN the user leaves the Display name, Title or Description field after changing it, THE SYSTEM SHALL validate and save only that field.
- R1.2 WHEN the user picks a Theme or Language, THE SYSTEM SHALL validate and save it immediately.
- R1.3 THE SYSTEM SHALL NOT show a Save button.
- R1.4 IF a value fails validation, THEN THE SYSTEM SHALL NOT save it and SHALL show the error under the field.

**R2 Toast feedback**
- R2.1 WHEN a text field is saved, THE SYSTEM SHALL show a green *success* toast: "{Field} saved."
- R2.2 WHEN Theme or Language is saved, THE SYSTEM SHALL show a blue *info* toast: "Theme changed to Dark." / "Language changed to 日本語."
- R2.3 IF validation fails, THEN THE SYSTEM SHALL show a red *error* toast with the validation message.
- R2.4 THE toast component SHALL also support an amber *warning* type for future use.

**R3 Toast behaviour**
- R3.1 THE toast SHALL fade in on appear and fade out on dismiss (opacity only, ~300ms).
- R3.2 THE toast SHALL dismiss itself after `duration` ms (default 3000), or when × is clicked.
- R3.3 WHEN several toasts fire in a row, THE SYSTEM SHALL stack them in the top-right corner, each with its own timer.
- R3.4 THE toast SHALL be announced to screen readers (`role="status"`, `aria-live="polite"`) and not flash on page load (`x-cloak`).

**Out of scope:** applying the chosen theme or language to the UI; server-side flash toasts on redirect.

## 3. Design

### 3.1 Event contract
Livewire components dispatch a browser event, default name `toast`:
```php
$this->dispatch('toast', type: 'success', message: 'Display name saved.');
```
Payload `{ type: 'success'|'info'|'warning'|'error', message: string }`; `type` defaults to `success`.

### 3.2 `<x-toast>` (Blade + Alpine, `resources/views/components/toast.blade.php`)
- Props unchanged: `event = 'toast'`, `duration = 3000`; extra classes via `$attributes->class(...)`.
- Container: `fixed right-4 top-4 z-50 flex flex-col gap-2 pointer-events-none`, `x-cloak`, `role="status"`, `aria-live="polite"`.
- Alpine state: `toasts: []`, `duration`, plus
  - `add(detail)`: push `{ id, type, message, isVisible: false }`; on `$nextTick` set `isVisible = true` so the enter fade runs; `setTimeout(() => dismiss(id), duration)`.
  - `dismiss(id)`: set `isVisible = false` so the leave fade runs; remove from `toasts` after 300ms.
- `<template x-for="toast in toasts" :key="toast.id">`, each item `x-show="toast.isVisible"` with
  `x-transition:enter="transition-opacity ease-out duration-300"` (opacity-0 → opacity-100) and
  `x-transition:leave="transition-opacity ease-in duration-300"` (opacity-100 → opacity-0); `pointer-events-auto`.
- Variants (class strings written literally so Tailwind compiles them):

| type | classes | icon (Heroicons 20 solid) |
|---|---|---|
| success | `bg-emerald-50 text-emerald-900 ring-emerald-200` | check-circle |
| info | `bg-sky-50 text-sky-900 ring-sky-200` | information-circle |
| warning | `bg-amber-50 text-amber-900 ring-amber-200` | exclamation-triangle |
| error | `bg-red-50 text-red-900 ring-red-200` | x-circle |

### 3.3 Profile settings page (`resources/views/pages/⚡profile-settings.blade.php`)
- Remove `save()`, `<form wire:submit>`, the bottom `<hr>` and the Save button; keep labels, `mount()`, `rules()` and inline `@error` messages.
- Header subtitle: "Changes are saved automatically."
- Bindings: Display name, Title, Description → `wire:model.live.blur` (one save and one toast per edit, not per keystroke; in Livewire 4.1+ plain `.blur` only syncs client-side and sends no request). Theme radios and Language select → `wire:model.live`.
- `updated()` follows the meeting notes page pattern, plus toasts:
```php
public function updated(string $property): void
{
    try {
        $this->validateOnly($property);
    } catch (ValidationException $exception) {
        $this->dispatch('toast', type: 'error', message: $exception->validator->errors()->first($property));

        throw $exception;
    }

    $this->profile->update([Str::snake($property) => $this->{$property}]);

    $this->dispatch('toast', ...$this->savedToast($property));
}
```
- `savedToast(string $property): array` (PHPDoc `@return array{type: string, message: string}`):
  `theme` → info, `Theme::from(..)->label()`; `language` → info, `Language::from(..)->label()`; otherwise success with labels Display name / Title / Description.

## 4. Tasks
- [x] T1 Rewrite `toast.blade.php` as a stacked, fading, multi-colour toast list (R2.4, R3).
- [x] T2 Update `ToastComponentTest`: default event and duration, all four variant classes present, custom `event` and `duration`.
- [x] T3 Convert the profile settings page to per-field autosave with typed toasts (R1, R2.1–R2.3).
- [x] T4 Update `ProfileSettingsPageTest`: no Save button; each field saves on change with the right toast type and message; invalid display name, theme and language are not saved and dispatch an error toast.
- [x] T5 `vendor/bin/pint --dirty --format agent`; run the profile, toast and meeting tests.
- [x] T6 `npm run build`; check `/profiles/1` returns 200 on Herd.

## 5. Verification
- Automated: `php artisan test --compact tests/Feature/ProfileSettingsPageTest.php tests/Feature/ProfileTest.php tests/Feature/ToastComponentTest.php tests/Feature/MeetingNotesPageTest.php`.
- Manual (http://laravel-livewire-demo.test/profiles/1): edit Display name and tab away → green toast fades in and out; switch Theme → blue toast; clear Display name and tab away → red toast plus inline error; quick edits stack.
- Then ask the user to run the full suite: `php artisan test --compact`.
