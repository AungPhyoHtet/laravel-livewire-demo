<?php

use App\Enums\ProductCategory;
use App\Models\Product;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Open the wizard and fill in the given steps with valid values.
 */
function wizardOnStep(int $step): Testable
{
    $wizard = Livewire::test('create-product-wizard')->call('open');

    if ($step >= 2) {
        $wizard->set('name', 'Desk lamp')
            ->set('category', 'home_and_kitchen')
            ->set('description', 'Warm LED lamp.')
            ->call('nextStep');
    }

    if ($step >= 3) {
        $wizard->set('price', '1234.5')
            ->set('url', 'https://example.com/lamp')
            ->call('nextStep');
    }

    return $wizard->assertSet('step', $step);
}

test('the wizard is closed until the create product button is clicked', function () {
    Livewire::test('create-product-wizard')
        ->assertSee('Create Product')
        ->assertSet('isOpen', false)
        ->call('open')
        ->assertSet('isOpen', true)
        ->assertSet('step', 1)
        ->assertSee('Step 1 of 3');
});

test('step one requires a name and a valid category before moving on', function () {
    wizardOnStep(1)
        ->call('nextStep')
        ->assertHasErrors(['name' => 'required', 'category' => 'required'])
        ->assertSet('step', 1)
        ->set('name', 'Desk lamp')
        ->set('category', 'toys')
        ->call('nextStep')
        ->assertHasErrors(['category'])
        ->assertSet('step', 1);
});

test('step one does not validate fields from later steps', function () {
    wizardOnStep(1)
        ->set('name', 'Desk lamp')
        ->set('category', 'books')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2);
});

test('step two requires a valid price and url before moving on', function (string $price, ?string $url, array $errors) {
    wizardOnStep(2)
        ->set('price', $price)
        ->set('url', $url)
        ->call('nextStep')
        ->assertHasErrors($errors)
        ->assertSet('step', 2);
})->with([
    'missing price' => ['', null, ['price' => 'required']],
    'non numeric price' => ['abc', null, ['price' => 'numeric']],
    'negative price' => ['-1', null, ['price' => 'min']],
    'invalid url' => ['10', 'not a url', ['url']],
    'non http url' => ['10', 'ftp://example.com/file', ['url']],
]);

test('the url is optional', function () {
    wizardOnStep(2)
        ->set('price', '10')
        ->set('url', null)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);
});

test('going back keeps the entered values', function () {
    wizardOnStep(2)
        ->call('previousStep')
        ->assertSet('step', 1)
        ->assertSet('name', 'Desk lamp')
        ->assertSet('category', 'home_and_kitchen');
});

test('the review step summarises every value', function () {
    wizardOnStep(3)
        ->assertSee('Desk lamp')
        ->assertSee('Home &amp; Kitchen', false)
        ->assertSee('Warm LED lamp.')
        ->assertSee('$1,234.50')
        ->assertSee('https://example.com/lamp')
        ->assertSee('Create product');
});

test('the review step shows a dash for empty optional fields', function () {
    Livewire::test('create-product-wizard')
        ->call('open')
        ->set('name', 'Desk lamp')
        ->set('category', 'books')
        ->call('nextStep')
        ->set('price', '5')
        ->call('nextStep')
        ->assertSet('step', 3)
        ->assertSee('—');
});

test('edit on the review step goes back to that step and keeps the values', function (int $step) {
    wizardOnStep(3)
        ->call('editStep', $step)
        ->assertSet('step', $step)
        ->assertSet('name', 'Desk lamp')
        ->assertSet('price', '1234.5');
})->with([1, 2]);

test('edit cannot jump forward to a step that has not been validated', function () {
    wizardOnStep(1)
        ->call('editStep', 3)
        ->assertSet('step', 1);
});

test('the step cannot be changed from the browser', function () {
    wizardOnStep(1)->set('step', 3);
})->throws(CannotUpdateLockedPropertyException::class);

test('creating saves the product, closes the wizard and shows a toast', function () {
    wizardOnStep(3)
        ->call('create')
        ->assertHasNoErrors()
        ->assertDispatched('product-created')
        ->assertDispatched('toast', type: 'success', message: 'Product created.')
        ->assertSet('isOpen', false)
        ->assertSet('step', 1)
        ->assertSet('name', '');

    expect(Product::sole())
        ->name->toBe('Desk lamp')
        ->category->toBe(ProductCategory::HomeAndKitchen)
        ->description->toBe('Warm LED lamp.')
        ->price->toBe('1234.50')
        ->url->toBe('https://example.com/lamp');
});

test('submitting the review step creates the product', function () {
    wizardOnStep(3)->call('submitStep');

    expect(Product::count())->toBe(1);
});

test('creating validates every field', function () {
    Livewire::test('create-product-wizard')
        ->call('open')
        ->call('create')
        ->assertHasErrors(['name', 'category', 'price'])
        ->assertNotDispatched('product-created');

    expect(Product::count())->toBe(0);
});

test('cancelling the wizard discards the draft', function () {
    wizardOnStep(2)
        ->call('close')
        ->assertSet('isOpen', false)
        ->assertSet('step', 1)
        ->assertSet('name', '')
        ->assertSet('category', '')
        ->call('open')
        ->assertSet('name', '');

    expect(Product::count())->toBe(0);
});

test('the draft, step and open wizard are restored after a page reload', function () {
    wizardOnStep(2)->set('price', '19.99');

    Livewire::test('create-product-wizard')
        ->assertSet('isOpen', true)
        ->assertSet('step', 2)
        ->assertSet('name', 'Desk lamp')
        ->assertSet('category', 'home_and_kitchen')
        ->assertSet('description', 'Warm LED lamp.')
        ->assertSet('price', '19.99')
        ->assertSee('Step 2 of 3');
});

test('dismissing the wizard hides it but keeps the draft', function () {
    wizardOnStep(2)
        ->call('dismiss')
        ->assertSet('isOpen', false)
        ->assertSet('step', 2)
        ->assertSet('name', 'Desk lamp')
        ->call('open')
        ->assertSet('isOpen', true)
        ->assertSet('step', 2)
        ->assertSet('name', 'Desk lamp');
});

test('a dismissed draft is still there after a page reload', function () {
    wizardOnStep(2)->call('dismiss');

    Livewire::test('create-product-wizard')
        ->assertSet('isOpen', false)
        ->assertSet('name', 'Desk lamp')
        ->call('open')
        ->assertSet('step', 2);
});

test('escape, the close button and the backdrop dismiss while cancel discards', function () {
    Livewire::test('create-product-wizard')
        ->call('open')
        ->assertSeeHtml('x-on:keydown.escape.window="$wire.isOpen && $wire.dismiss()"')
        ->assertSeeHtmlInOrder(['wire:click="dismiss"', 'bg-stone-900/40'])
        ->assertSeeHtmlInOrder(['wire:click="dismiss"', 'aria-label="Close"'])
        ->assertSeeHtmlInOrder(['wire:click="close"', 'Cancel']);
});

test('cancelling the wizard clears the saved draft', function () {
    wizardOnStep(2)->call('close');

    Livewire::test('create-product-wizard')
        ->assertSet('isOpen', false)
        ->assertSet('step', 1)
        ->assertSet('name', '');
});

test('creating a product clears the saved draft', function () {
    wizardOnStep(3)->call('create');

    Livewire::test('create-product-wizard')
        ->assertSet('isOpen', false)
        ->assertSet('step', 1)
        ->assertSet('name', '')
        ->assertSet('price', '');
});

test('the inputs send the draft to the server while typing', function () {
    Livewire::test('create-product-wizard')
        ->call('open')
        ->assertSeeHtml('wire:model.live.debounce.500ms="name"')
        ->assertSeeHtml('wire:model.live="category"');
});
