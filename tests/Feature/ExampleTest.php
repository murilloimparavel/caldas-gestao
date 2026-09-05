<?php

it('renders the public marketing landing page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('marketing/home'));
});
