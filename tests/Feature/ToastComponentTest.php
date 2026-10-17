<?php

test('the toast listens for the toast event by default', function () {
    $this->blade('<x-toast />')
        ->assertSee('x-on:toast.window', false)
        ->assertSee('setTimeout(() => isVisible = false, 3000)', false)
        ->assertSee('role="status"', false);
});

test('the toast event and duration can be customised', function () {
    $this->blade('<x-toast event="meeting-saved" :duration="5000" />')
        ->assertSee('x-on:meeting-saved.window', false)
        ->assertSee('setTimeout(() => isVisible = false, 5000)', false);
});
