# Spec: Posts list with infinite scroll, search and category filter, and post details page

**Status:** Done · **Date:** 2026-10-18 · **Owner:** aungphyohtet

---

## 1. Context
The user wants two pages:
- **Posts list:** infinite scroll, a search box and a category select box.
- **Post details:** one post in full.

The app has no posts yet. Both pages match the white-card UI (`⚡products`, `⚡profile-settings`).

## 2. Requirements (EARS)

**R1 Posts list (`/posts`, `posts.index`)**
- R1.1 THE SYSTEM SHALL list published posts, newest first. Each item shows its category, title (linked to its details page), excerpt, author, published date and reading time.
- R1.2 THE list SHALL show 12 posts at first. WHEN the user scrolls near the end of the list, THE SYSTEM SHALL load the next 12 without a page reload, and repeat until every matching post is shown.
- R1.3 WHILE more posts are loading, THE list SHALL show "Loading more posts…". WHEN every matching post is shown, it SHALL show "You've reached the end." A "Load more posts" button SHALL also be available, for keyboards and screen readers.
- R1.4 WHEN the user types in the search box, THE SYSTEM SHALL filter posts whose title or excerpt contains the text (case-insensitive, debounced). `%` and `_` are matched literally.
- R1.5 WHEN the user picks a category from the select box, THE SYSTEM SHALL show only posts in that category. "All categories" removes the filter.
- R1.6 Search and category SHALL combine. Changing either SHALL start again from the first 12 posts.
- R1.7 Search and category SHALL be kept in the URL (`?q=…&category=…`), so a filtered list can be shared, reloaded and reached with the back button. An unknown category in the URL is ignored.
- R1.8 THE list SHALL show a count ("42 posts", or "3 posts matching “laravel”"). WHEN nothing matches, it SHALL show "No posts match your filters." and a "Clear filters" button that resets both filters.
- R1.9 Draft posts (no published date, or one in the future) SHALL never be listed.

**R2 Post details (`/posts/{slug}`, `posts.show`)**
- R2.1 THE SYSTEM SHALL show the post's category, title, author, published date, reading time and body paragraphs, with a back link to the posts list.
- R2.2 THE page SHALL show up to 3 other published posts in the same category ("More in Design").
- R2.3 A missing or draft post SHALL return 404.

**Out of scope:** creating or editing posts, comments, authors as users and pagination links.

## 3. Design

### 3.1 Data
- `app/Enums/PostCategory.php`, a string enum with `label()`: `Engineering`, `Design`, `Product`, `Culture`, `News`.
- `posts` table: `id`, `title`, `slug` (unique), `category`, `excerpt` (string 500), `body` (text), `author_name`, `published_at` (nullable, indexed), timestamps.
- `App\Models\Post`:
  - `#[Fillable]`, with casts `category => PostCategory` and `published_at => datetime`.
  - `#[Scope] published()`, for posts with a published date that has passed.
  - `paragraphs()` splits the body on blank lines.
  - `readingTime()` is the minutes at 200 words a minute, at least 1.
  - `getRouteKeyName()` stays the id; the route uses `{post:slug}`.
- Factory with a `draft()` state. `PostSeeder` creates 200 published posts spread over the past year (40 hand-written across the categories, 160 from the factory), plus 2 drafts. It is called from `DatabaseSeeder`.

### 3.2 List page: `resources/views/pages/⚡posts.blade.php`
- State:
  - `#[Url(as: 'q', except: '')] public string $search = ''`;
  - `#[Url(except: '')] public string $category = ''`;
  - `public int $page = 1` (pages of `PAGE_SIZE` = 12).
- The list rows are a named island, `@island(name: 'posts')`, inside an `<ol>` that is always rendered (hidden when nothing matches), because islands can't sit inside conditionals.
- `loadMore()` increments `page` and calls `renderIsland('posts', mode: 'append')`, so only the next 12 rows are queried and sent, and they are appended to the island.
- `updatedSearch()`, `updatedCategory()` and `clearFilters()` reset `page` to 1 and call `renderIsland('posts')` (morph). Islands are skipped when the rest of the component re-renders, so this explicit render is what replaces the rows.
- The rest of the component (summary, empty state, sentinel, end message) still re-renders on every request. Livewire's morph leaves the skipped island's rows untouched.
- `#[Computed] query()` builds the filtered query:
  - published posts only;
  - search uses `LIKE ? ESCAPE '!'` on title or excerpt;
  - category uses `PostCategory::tryFrom`, so an unknown value is ignored.
- `#[Computed] posts()` is the current page (`forPage($page, 12)`), ordered by `published_at desc, id desc`. It is only read inside the island. `#[Computed] total()` is the count.
- `hasMore()` (`total > page × 12`) is a plain method, not computed. `loadMore()` checks it and then raises `page`, so a cached value would still say "more" when the render happens, and the sentinel would never disappear.
- Markup:
  - The search input uses `wire:model.live.debounce.300ms`, `type="search"`, with a label (sr-only) and a magnifier icon.
  - The category `<select>` uses `wire:model.live`.
  - The results are an `<ol>` of `<article>` rows with `wire:key`.
- Infinite scroll: while `posts < total`, a sentinel `<div wire:intersect.margin.300px="loadMore" wire:key="sentinel-{{ $page }}">` holds "Load more posts" (`wire:click="loadMore"`) and the `wire:loading` text. The `wire:key` changes after every load, so the sentinel is re-created. If it is still on screen (a tall window), the new observer fires again and loads the next page.

### 3.3 Details page: `resources/views/pages/⚡post-show.blade.php`
- `Route::livewire('/posts/{post:slug}', 'pages::post-show')`. `mount(Post $post)` aborts with 404 unless the post is published.
- `#[Computed] relatedPosts()`: 3 published posts in the same category, excluding this one, newest first.
- An article card with a `prose`-like spacing of `<p>` paragraphs (no new dependency; just Tailwind classes).

## 4. Tasks
- [x] T1 `PostCategory` enum, `Post` model, migration, factory (`draft` state), seeder (+ `DatabaseSeeder`).
- [x] T2 `PostTest`: casts, published scope (past, future, null), paragraphs, reading time.
- [x] T3 List page `⚡posts` + route.
- [x] T4 `PostsPageTest`:
  - lists published posts newest first, with no drafts, and the first 12 only;
  - `loadMore` appends only the next 12 rows to the island (checked in the `islandFragments` effect), and the sentinel disappears at the end, which shows "You've reached the end.";
  - search on title and excerpt is case-insensitive, with wildcards escaped;
  - category filter, and the combination of both;
  - changing a filter resets `page` and re-renders the island from the first page;
  - an unknown category is ignored;
  - URL query string;
  - count text, the empty state and `clearFilters`;
  - the intersect markup.
- [x] T5 Details page `⚡post-show` + route.
- [x] T6 `PostShowPageTest`: shows the fields and paragraphs; related posts (same category, published, not itself, max 3); 404 for a missing post and for a draft; the back link.
- [x] T7 Pint; run the post tests.
- [x] T8 `migrate`, `db:seed --class=PostSeeder`, `npm run build`. Then a headless browser check:
  - scrolling loads more until the end;
  - search filters and updates the URL;
  - the category select filters, and both combine;
  - "Clear filters" works;
  - reloading a filtered URL keeps the filters;
  - clicking a post opens its details, with related posts;
  - no JS errors.

## 5. Verification
- Automated: `php artisan test --compact tests/Feature/PostTest.php tests/Feature/PostsPageTest.php tests/Feature/PostShowPageTest.php`.
- Browser: T8 against http://laravel-livewire-demo.test/posts.
- Then ask the user to run the full suite: `php artisan test --compact`.
