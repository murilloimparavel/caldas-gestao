<?php

it('redirects home visitors to the dashboard', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
