<?php

use App\Enums\Language;
use App\Enums\Theme;
use App\Models\Profile;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public Profile $profile;

    public string $displayName = '';

    public ?string $title = null;

    public ?string $description = null;

    public string $theme = '';

    public string $language = '';

    public function mount(Profile $profile): void
    {
        $this->profile = $profile;
        $this->displayName = $profile->display_name;
        $this->title = $profile->title;
        $this->description = $profile->description;
        $this->theme = $profile->theme->value;
        $this->language = $profile->language->value;
    }

    /**
     * Get the validation rules for the profile settings.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'displayName' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'theme' => ['required', Rule::enum(Theme::class)],
            'language' => ['required', Rule::enum(Language::class)],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $this->profile->update([
            'display_name' => $validated['displayName'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'theme' => $validated['theme'],
            'language' => $validated['language'],
        ]);

        $this->dispatch('toast', message: 'Profile settings saved.');
    }
};
?>

<div class="min-h-screen bg-stone-100 px-4 py-10 sm:py-16">
    <article class="mx-auto max-w-3xl rounded-lg bg-white px-6 py-8 shadow-sm ring-1 ring-stone-200 sm:px-12 sm:py-12">
        <header class="space-y-1">
            <h1 class="text-3xl font-semibold text-stone-900">Profile settings</h1>
            <p class="text-sm text-stone-500">Update how you appear and how the app looks for you.</p>
        </header>

        <hr class="my-6 border-stone-200">

        <form wire:submit="save" class="space-y-6">
            <div class="space-y-1.5">
                <label for="display-name" class="block text-sm font-medium text-stone-700">Display name</label>
                <input
                    id="display-name"
                    type="text"
                    wire:model="displayName"
                    placeholder="Alex Rivera"
                    class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-900 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                >
                @error('displayName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label for="title" class="block text-sm font-medium text-stone-700">Title</label>
                <input
                    id="title"
                    type="text"
                    wire:model="title"
                    placeholder="Product Designer"
                    class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-base text-stone-900 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                >
                @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1.5">
                <label for="description" class="block text-sm font-medium text-stone-700">Description</label>
                <textarea
                    id="description"
                    wire:model="description"
                    placeholder="Write a short description about yourself…"
                    rows="4"
                    class="field-sizing-content min-h-28 w-full resize-none rounded-md border border-stone-200 bg-white px-3 py-2 text-base leading-7 text-stone-800 placeholder:text-stone-300 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                ></textarea>
                @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <fieldset class="space-y-1.5">
                    <legend class="mb-1.5 block text-sm font-medium text-stone-700">Theme</legend>

                    <div class="inline-flex rounded-md bg-stone-100 p-1">
                        @foreach (Theme::cases() as $themeOption)
                            <label wire:key="theme-{{ $themeOption->value }}" class="cursor-pointer">
                                <input
                                    type="radio"
                                    name="theme"
                                    value="{{ $themeOption->value }}"
                                    wire:model="theme"
                                    class="peer sr-only"
                                >
                                <span class="block rounded px-3 py-1.5 text-sm text-stone-500 peer-checked:bg-white peer-checked:text-stone-900 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-stone-300">
                                    {{ $themeOption->label() }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('theme') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>

                <div class="space-y-1.5">
                    <label for="language" class="block text-sm font-medium text-stone-700">Language</label>
                    <select
                        id="language"
                        wire:model="language"
                        class="w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-sm text-stone-800 focus:border-stone-400 focus:outline-none focus:ring-2 focus:ring-stone-200"
                    >
                        @foreach (Language::cases() as $languageOption)
                            <option wire:key="language-{{ $languageOption->value }}" value="{{ $languageOption->value }}">{{ $languageOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('language') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <hr class="border-stone-200">

            <div class="flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    </article>
</div>
