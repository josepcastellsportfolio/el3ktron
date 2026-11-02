<?php

it('shows the landing instead of the Laravel welcome page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('El3ktron')
        ->assertSee('/api/v1/health')
        ->assertDontSee('Laravel has an incredibly rich ecosystem');
});
