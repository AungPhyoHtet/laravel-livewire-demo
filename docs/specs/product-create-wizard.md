# Spec: Create Product multi-step wizard modal

**Status:** Done · **Date:** 2026-10-18 · **Owner:** aungphyohtet

---

## 1. Context
The app has no products yet. The user wants a **Create Product** button that opens a **modal wizard** with three steps:
1. **Details**: name, category, description
2. **Pricing & link**: price, URL
3. **Review**: a summary of everything entered, with an **Edit** button per section that jumps back to that step

Finishing the wizard creates the product. It should match the existing white-card UI (`⚡meeting-notes`, `⚡profile-settings`) and reuse the `<x-toast />` component (`resources/views/components/toast.blade.php`, already in `layouts/app.blade.php`).

## 2. Requirements (EARS)

**R1 Products page**
- R1.1 THE SYSTEM SHALL show a products page at `/products` (`products.index`) listing products (name, category, price, URL) newest first, with an empty state when there are none.
- R1.2 THE page SHALL show a **Create Product** button.
- R1.3 WHEN a product is created, THE list SHALL refresh without a page reload.

**R2 Wizard modal**
- R2.1 WHEN the user clicks Create Product, THE SYSTEM SHALL open a modal on step 1 with empty fields.
- R2.2 THE modal SHALL show a 3-step indicator (Details, Pricing & link, Review) marking done, current and upcoming steps.
- R2.3 WHEN the user clicks Next, THE SYSTEM SHALL validate only the current step's fields and advance only if they are valid; errors show under each field.
- R2.4 WHEN the user clicks Back, THE SYSTEM SHALL return to the previous step and keep entered values.
- R2.5 THE Review step SHALL show all entered values (price formatted as `$1,234.50`, empty optional fields as "—") grouped by step, each group with an **Edit** button that returns to that step with values kept.
- R2.6 THE user SHALL NOT be able to jump forward past a step that hasn't been validated.
- R2.7 WHEN the user clicks Cancel, THE SYSTEM SHALL close the modal and discard the draft. WHEN the user clicks ×, the backdrop or presses Escape, THE SYSTEM SHALL hide the modal and keep the draft (see R4).
- R2.8 THE modal SHALL fade in and out, trap focus while open (`x-trap`), and use `role="dialog"`, `aria-modal`, `aria-labelledby`.

**R3 Create**
- R3.1 WHEN the user clicks **Create product** on Review, THE SYSTEM SHALL validate all fields, save the product, close and reset the wizard, refresh the list and show a green toast "Product created."
- R3.2 Validation: name required, max 255; category required, one of the categories; description optional, max 2000; price required, numeric, 0–999,999.99; URL optional, valid `http`/`https` URL, max 2048.

**R4 Draft survives reload** *(added 2026-10-18)*
- R4.1 WHEN the user reloads the page with the wizard open, THE SYSTEM SHALL reopen it on the same step with every entered value restored.
- R4.2 THE draft SHALL be saved while typing (debounced), so a reload without leaving the field keeps the latest value.
- R4.3 WHEN the user clicks Cancel or creates the product, THE SYSTEM SHALL clear the saved draft; dismissing with ×, the backdrop or Escape SHALL keep it.

**Out of scope:** editing or deleting products, images, authentication or ownership.

## 3. Design

### 3.1 Data
- `app/Enums/ProductCategory.php`, a string enum with `label()` like `Theme` and `Language`: `Electronics`, `Clothing`, `HomeAndKitchen` ("Home & Kitchen"), `Books`, `Sports`, `Other`.
- `products` table: `id`, `name`, `category` (string), `description` (text, nullable), `price` (decimal 10,2), `url` (string 2048, nullable), timestamps.
- `App\Models\Product`: `#[Fillable]`, casts `category => ProductCategory`, `price => decimal:2`, `@property` docblock like `Meeting` and `Profile`. Factory, plus a `ProductSeeder` with three sample products, called from `DatabaseSeeder`.

### 3.2 Products page: `resources/views/pages/⚡products.blade.php`
- `Route::livewire('/products', 'pages::products')->name('products.index')`.
- `#[Computed] products()` returns `Product::latest()->get()`.
- Same `bg-stone-100` page with a white `article` card. Header: "Products" plus a subtitle on the left, and `<livewire:create-product-wizard @product-created="$refresh" />` on the right (it renders the button and the modal).
- List: `divide-y` rows with `wire:key`. Name, category label badge, price and URL link (`target="_blank" rel="noopener"`). Empty state: "No products yet. Create your first one."

### 3.3 Wizard: `resources/views/components/⚡create-product-wizard.blade.php` (Livewire SFC)
State:
```php
public bool $isOpen = false;
#[Locked] public int $step = 1;
public string $name = '';
public string $category = '';
public ?string $description = null;
public string $price = '';
public ?string $url = null;
```
- `rules()` holds all rules (R3.2), with `Rule::enum(ProductCategory::class)` and `url:http,https`.
- `stepFields(int $step): array<int, string>`: `1 => [name, category, description]`, `2 => [price, url]`.
- `open()`: reset the draft, `step = 1`, `isOpen = true`.
- `close()`: `isOpen = false`, reset the draft and validation.
- `nextStep()`: validate only `array_intersect_key($this->rules(), array_flip($this->stepFields($this->step)))`, then `step++` (max 3).
- `previousStep()`: `step--` (min 1).
- `editStep(int $step)`: only allowed when `1 <= $step < $this->step`, which covers R2.6.
- `create()`: `$this->validate()`, `Product::create([...])`, `dispatch('product-created')`, `dispatch('toast', type: 'success', message: 'Product created.')`, `close()`.
- R4: `isOpen`, `step` and every field carry `#[Session]` (stored in the user's session, restored on mount). `open()` no longer resets the draft. `dismiss()` (×, backdrop, Escape) only hides the wizard; `close()` (Cancel) and `create()` discard the draft.
- Inputs use `wire:model.live.debounce.500ms` (the category select uses `wire:model.live`) so the session draft stays current while typing.

Markup:
- Button: dark stone pill "Create Product" with `wire:click="open"`.
- Modal: `wire:show="isOpen"` with `x-transition.opacity.duration.200ms` (fade), fixed backdrop `bg-stone-900/40`, and a centred white `max-w-xl` panel (`rounded-lg ring-1 ring-stone-200 shadow-xl`). It has `x-trap.noscroll="$wire.isOpen"` and `x-on:keydown.escape.window="$wire.isOpen && $wire.close()"`. Clicking the backdrop calls `close`.
- Header: title "Create product" (`id` for `aria-labelledby`), × close button, and a step indicator (numbered circles and labels; done = check and dark, current = dark ring, upcoming = stone-300).
- Step bodies use `@if ($step === 1)` / `2` / `3`. Labelled inputs follow the `⚡profile-settings` input classes. The price input has a `$` prefix with `inputmode="decimal"`. Category is a `<select>` with a placeholder option.
- Review: two `dl` groups ("Details", "Pricing & link"), each with an "Edit" text button using `wire:click="editStep(n)"`.
- Footer: on step 1, Cancel; otherwise Back. On steps 1–2, Next; on step 3, **Create product** (with `wire:loading` "Creating…").

## 4. Tasks
- [x] T1 `ProductCategory` enum, `Product` model, migration, factory, seeder (+ `DatabaseSeeder`). Generate with `php artisan make:model Product -mfs` and `make:enum`, checking the enum lands in `app/Enums/` (last time `make:enum` nested it).
- [x] T2 `ProductTest`: create with all fields, optional description and URL, enum and decimal casts.
- [x] T3 Wizard component `⚡create-product-wizard` (button, modal, 3 steps, review with edit, create).
- [x] T4 `CreateProductWizardTest`: opens on step 1; Next blocked by invalid step fields (name/category; price/URL); advances when valid; Back keeps values; review shows values; Edit goes back and keeps values; Edit can't jump forward; create saves, dispatches `product-created` and a success toast, closes and resets; close discards the draft.
- [x] T5 Products page `⚡products` + route.
- [x] T6 `ProductsPageTest`: lists products newest first with formatted price, shows the Create Product button, shows the empty state, refreshes on `product-created`.
- [x] T7 `vendor/bin/pint --dirty --format agent`; run product, profile, toast and meeting tests.
- [x] T8 `php artisan migrate`, `db:seed --class=ProductSeeder`, `npm run build`; browser-check `/products` with headless Chromium (scratchpad Playwright script, as before): open the modal, Next with empty fields shows errors, fill both steps, review shows values, Edit returns to step 1, Create shows a toast and a new row, Escape closes. Take a screenshot of each step.

- [x] T9 R4: `#[Session]` on wizard state, live debounced inputs, reload tests (restore, reopen keeps draft, close and create clear it), browser reload check.
- [x] T10 Split closing: `dismiss()` for ×/backdrop/Escape keeps the draft, Cancel discards; tests and browser check.

## 5. Verification
- Automated: `php artisan test --compact tests/Feature/ProductTest.php tests/Feature/ProductsPageTest.php tests/Feature/CreateProductWizardTest.php` plus the existing profile, toast and meeting tests.
- Browser: T8 against http://laravel-livewire-demo.test/products.
- Then ask the user to run the full suite: `php artisan test --compact`.
