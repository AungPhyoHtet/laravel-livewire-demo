# Spec: Product tags page with autocomplete tag input

**Status:** Done · **Date:** 2026-10-18 · **Owner:** aungphyohtet

---

## 1. Context
The user wants a **tag input with autocomplete** on a new page. Tags need something to belong to, so this spec tags the existing **products**. Each product gets a tags page at `/products/{product}/tags`, opened from a "Tags" link on each row of the products list.

The page matches the white-card UI (`⚡profile-settings`, `⚡products`). Like profile settings, it autosaves each change and confirms it with the `<x-toast />` component.

## 2. Requirements (EARS)

**R1 Tags page**
- R1.1 THE SYSTEM SHALL show a tags page for a product at `/products/{product}/tags` (`products.tags`), with the product's name, a back link to the products list and the product's current tags. A missing product returns 404.
- R1.2 THE products list SHALL show each product's tags and a "Tags" link to its tags page.

**R2 Tag input**
- R2.1 THE tag input SHALL show the product's tags as chips, in the order they were added, followed by a text field.
- R2.2 WHEN the user types, THE SYSTEM SHALL suggest up to 6 existing tags that contain the typed text (case-insensitive) and aren't on the product yet. Tags that start with the text come first, then the rest alphabetically.
- R2.3 WHEN the user presses Enter or comma, THE SYSTEM SHALL add the highlighted suggestion, or the typed text if no suggestion is highlighted.
- R2.4 WHEN the user clicks a suggestion, THE SYSTEM SHALL add it.
- R2.5 ArrowDown and ArrowUp SHALL move the highlight through the suggestions, and Escape SHALL close the list.
- R2.6 WHEN the user presses Backspace in an empty text field, THE SYSTEM SHALL remove the last tag.
- R2.7 WHEN the user clicks a chip's ×, THE SYSTEM SHALL remove that tag.
- R2.8 THE input SHALL follow the ARIA combobox pattern (`role="combobox"`, `aria-expanded`, `aria-controls`, `aria-activedescendant`, a `listbox` of `option`s), and each chip's × SHALL have an `aria-label` ("Remove audio").
- R2.9 THE suggestions list SHALL show "Press Enter to add “…”" when the typed text is a valid name that doesn't exactly match an existing tag.

**R3 Saving**
- R3.1 Tag names are squished (outer and repeated spaces removed) and lowercased. A name matching an existing tag reuses it, so no duplicate tags exist.
- R3.2 WHEN a tag is added, THE SYSTEM SHALL save it immediately, clear the text field and show a green toast "Added “audio”."
- R3.3 WHEN a tag is removed, THE SYSTEM SHALL save immediately and show a blue toast "Removed “audio”." The tag itself stays available as a suggestion.
- R3.4 WHEN the tag is already on the product, THE SYSTEM SHALL not add it again and SHALL show an amber toast "“audio” is already added."
- R3.5 Validation: a name is at most 30 characters and can't contain commas; a product has at most 10 tags. A failure shows a red toast and the error under the input ("Tags can be at most 30 characters.", "Tags can’t contain commas.", "A product can have up to 10 tags."), and nothing is saved. Empty input does nothing.

**Out of scope:** renaming or deleting tags globally, filtering products by tag, and tags on anything other than products.

## 3. Design

### 3.1 Data
- `tags` table: `id`, `name` (string 30, unique), timestamps.
- `product_tag` pivot: `id`, `product_id` and `tag_id` (foreign keys, cascade on delete), timestamps, unique `[product_id, tag_id]`. The pivot `id` gives the added-order (R2.1).
- `App\Models\Tag`: `#[Fillable(['name'])]`, `products()` belongsToMany, plus a factory.
- `Product::tags()`: belongsToMany, `withTimestamps()`, `orderBy('product_tag.id')`.
- `TagSeeder`: sample tags attached to the three seeded products, plus a few unattached tags so suggestions show. Called from `DatabaseSeeder` after `ProductSeeder`.

### 3.2 Page: `resources/views/pages/⚡product-tags.blade.php` (Livewire SFC)
- `Route::livewire('/products/{product}/tags', 'pages::product-tags')->name('products.tags')`.
- State: `#[Locked] public Product $product`, `public string $query = ''`, and `public const MAX_TAGS = 10`.
- `#[Computed] tags()`: `$this->product->tags`, fresh after each change.
- `#[Computed] suggestions()`: an empty query returns an empty collection. Otherwise, filter tags with `name LIKE ? ESCAPE '\'` (escaping `%`, `_` and `\`) and exclude the product's tags. Order by `CASE WHEN name LIKE 'q%' THEN 0 ELSE 1 END`, then `name`, and limit to 6.
- `addTag(string $name)`: normalise (R3.1), return early if empty, validate with `Validator::make` (R3.5), and check the limit and duplicates. Then `Tag::firstOrCreate`, `attach`, reset `query` and dispatch the toast. On failure, `addError('query', …)` and dispatch an error toast. The error is keyed to `query` because Livewire only keeps errors between requests when they belong to a component property; a `tag` key was dropped by the next debounced request.
- `removeTag(int $tagId)`: detach from this product only, then dispatch the info toast.
- Markup: a bordered box (`focus-within` ring like the other inputs) with chips (`rounded-full bg-stone-100`, × button) and an input `wire:model.live.debounce.250ms="query"`. A suggestions `ul role="listbox"` sits absolutely under the box.
- Alpine (`x-data`) holds only `open` and `highlighted` (the default is `-1`, none). Enter/comma call `$wire.addTag(highlighted name ?? $wire.query)`. Backspace on empty input calls `$wire.removeTag(last chip id)`. Typing resets the highlight to none, so Enter never picks a suggestion that went stale while the request was in flight.
- Hint below: "Press Enter or comma to add a tag, Backspace to remove the last one." Counter: "3 / 10 tags".

### 3.3 Products list
- Eager-load `tags`. Each row shows its tags as small chips and a "Tags" link (`route('products.tags', $product)`).

## 4. Tasks
- [x] T1 `Tag` model, migration, factory, seeder; `product_tag` pivot migration; `Product::tags()`; `DatabaseSeeder`.
- [x] T2 `TagTest`: name unique; product tags in added order; deleting a product removes its pivot rows only.
- [x] T3 Page `⚡product-tags` + route.
- [x] T4 `ProductTagsPageTest`: shows the product and its tags; 404; add a new tag (normalised, toast); reuse an existing tag case-insensitively; an already-added tag shows a warning; empty does nothing; too long, comma and limit errors; remove (detach, toast, tag kept); remove only affects this product; suggestions (match, exclude attached, prefix first, limit 6, wildcards escaped); combobox ARIA markup; seeder.
- [x] T5 Products list shows tags and the Tags link; extend `ProductsPageTest`.
- [x] T6 Pint; run tag, product and profile tests.
- [x] T7 `migrate`, `db:seed --class=TagSeeder`, `npm run build`; headless browser check: type to see suggestions, arrow+Enter adds, click adds, Enter adds new, comma adds, Backspace removes last, × removes, toasts show, no JS errors.

## 5. Verification
- Automated: `php artisan test --compact tests/Feature/TagTest.php tests/Feature/ProductTagsPageTest.php tests/Feature/ProductsPageTest.php`.
- Browser: T7 against http://laravel-livewire-demo.test/products/1/tags.
- Then ask the user to run the full suite: `php artisan test --compact`.
