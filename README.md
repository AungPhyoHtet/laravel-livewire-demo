# Laravel Livewire Demo

A collection of small, self-contained pages built with **Laravel 13** and **Livewire 4**. Each one shows a different Livewire pattern: autosaving forms, a multi-step modal wizard, autocomplete, islands with infinite scroll, polling, a keyboard-driven search palette and a drag-and-drop Kanban board.

There is no authentication. Pages that belong to a record (a meeting, a profile, a user) take it from the URL.

## Stack

- PHP 8.3, Laravel 13, SQLite
- Livewire 4 single-file page components (`resources/views/pages/⚡*.blade.php`), with the Alpine.js that ships with Livewire
- Tailwind CSS 4, built with Vite
- Pest 4, Pint and Larastan

## Getting started

```bash
composer run setup      # install dependencies, create .env, generate the key, migrate, build assets
php artisan db:seed     # sample data for every page
composer run dev        # start the app, queue, logs and Vite
```

With [Laravel Herd](https://herd.laravel.com) the app is served at `http://laravel-livewire-demo.test`. Otherwise `composer run dev` serves it at `http://localhost:8000`.

## Pages

The IDs below match the seeded data.

| Page | URL | What it shows |
| --- | --- | --- |
| Meeting notes | `/meetings/1` | Title, date and notes saved as you edit, with validation per field |
| Profile settings | `/profiles/1` | Per-field autosave with coloured, fading toasts |
| Products | `/products` | Products list with a three-step **Create Product** wizard in a modal |
| Product tags | `/products/1/tags` | Tag input with autocomplete, keyboard selection and autosave |
| Posts | `/posts` | 200 posts with search, a category filter and infinite scroll, loaded through a Livewire island |
| Post details | `/posts/{slug}` | A post with related posts from the same category |
| Dashboard | `/users/1/dashboard` | Jira-style notifications dropdown polled every 10 seconds |
| Board | `/board` | Kanban board with drag and drop, keyboard controls, and create, edit and delete |

### Posts

- Search and the category filter are kept in the URL (`?q=…&category=…`).
- The list is a named island, `@island(name: 'posts')`. Loading more appends only the next 12 posts to it (`renderIsland('posts', mode: 'append')`), and searching or filtering re-renders it from the first page.

### Quick search

On the posts list and post pages, **⌘K** / **Ctrl K** or **/** opens a search palette, much like on documentation sites. Results update as you type and come from post titles, excerpts and body text. They're grouped by category, with the matches highlighted.

| Key | Action |
| --- | --- |
| **↑** / **↓** | Move through the results |
| **Enter** | Open the selected post |
| **Esc** | Close |

### Dashboard notifications

- The bell shows how many notifications are unread. Its dropdown groups them under Today, Yesterday and Older, and can show only unread ones.
- You can mark a single notification read or unread, or mark them all as read.
- The page polls every 10 seconds (`wire:poll.10s`). To see a new notification arrive, send one from the terminal:

```bash
php artisan app:send-test-notification 1 --title="Build finished" --message="Deploy #42 is live."
```

### Board

The board has six columns: To do, In progress, Reopen, Code merged, Testing and Done. Cards can be dragged within a column or between columns, using Livewire's `wire:sort`. The new order is saved immediately.

| Key (on a focused card) | Action |
| --- | --- |
| **Tab** | Focus a card |
| **↑** / **↓** / **←** / **→** | Move focus between cards |
| **Shift** + arrows | Move the card up, down, or to the next column |
| **Enter** | Edit the card |
| **Delete** / **Backspace** | Delete the card (after confirmation) |
| **N** | New task in the focused card's column (To do if no card is focused) |

- New tasks can also be added with the **New task** button or with **+ Add task** under each column.
- Each move, create and delete is announced to screen readers.

## Testing

```bash
php artisan test --compact   # the Pest suite
composer test                # Pint check, Larastan, then the Pest suite
composer run lint            # fix code style with Pint
```

## Project layout

```
app/Enums/                        PostCategory, ProductCategory, TaskStatus, Theme, Language
app/Models/                       Meeting, Profile, Product, Tag, Post, Task, User
app/Notifications/                ActivityNotification (database channel)
app/Console/Commands/             app:send-test-notification
resources/views/pages/            Full-page Livewire components, one per page
resources/views/components/       Create Product wizard, post search palette, toast
database/seeders/                 Sample data for every page
docs/specs/                       Feature specs
tests/Feature/                    Pest tests for every page, model and command
```

## Specs

Some features have a written spec in [`docs/specs`](docs/specs):

- [Posts list and post details](docs/specs/posts-list-and-details.md)
- [Create Product wizard](docs/specs/product-create-wizard.md)
- [Product tags](docs/specs/product-tags.md)
- [Profile settings autosave](docs/specs/profile-settings-autosave.md)
