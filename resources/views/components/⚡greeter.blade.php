<?php

use Livewire\Component;

new class extends Component {
    public string $name = 'World';

    public function changeName(string $newName): void
    {
        $this->name = $this->name === 'World' ? 'Livewire' : 'World';
    }
};
?>

<div>
    <h1 class="mb-4 text-xl font-medium dark:text-white">Hello, {{ $name }}!</h1>

    <button class="text-white font-medium rounded-md px-4 py-2 bg-blue-500" wire:click="changeName('Livewire')">Greet
        Livewire</button>
</div>
