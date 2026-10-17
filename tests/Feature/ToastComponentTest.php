<?php

test('the toast listens for the toast event by default', function () {
    $this->blade('<x-toast />')
        ->assertSee('x-on:toast.window', false)
        ->assertSee('duration: 3000', false)
        ->assertSee('role="status"', false);
});

test('the toast has a colour for each type', function () {
    $this->blade('<x-toast />')
        ->assertSee('bg-emerald-50', false)
        ->assertSee('bg-sky-50', false)
        ->assertSee('bg-amber-50', false)
        ->assertSee('bg-red-50', false);
});

test('the toast fades in and out', function () {
    $this->blade('<x-toast />')
        ->assertSee('x-transition:enter="transition-opacity ease-out duration-300"', false)
        ->assertSee('x-transition:leave="transition-opacity ease-in duration-300"', false);
});

test('the toast event and duration can be customised', function () {
    $this->blade('<x-toast event="meeting-saved" :duration="5000" />')
        ->assertSee('x-on:meeting-saved.window', false)
        ->assertSee('duration: 5000', false);
});
