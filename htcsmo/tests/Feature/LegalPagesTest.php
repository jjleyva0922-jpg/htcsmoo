<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_are_publicly_accessible(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Authorized use');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Information collected');
    }

    public function test_login_and_registration_link_to_the_legal_pages(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false);
    }
}
