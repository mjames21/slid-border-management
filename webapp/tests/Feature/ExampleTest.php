<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_renders_public_welcome_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('BorderReach')
            ->assertSee('Make every remote border visible')
            ->assertSee('single window to report, supervise, and analyze hard-to-reach border posts')
            ->assertSee('Travel documents')
            ->assertSee('ICAO Doc 9303')
            ->assertSee('Passport, visa, MRZ, VIZ, and document-security checks')
            ->assertSee('Explore Product')
            ->assertSee('Customs reports')
            ->assertSee('WCO Data Model')
            ->assertSee('Health screening')
            ->assertSee('WHO IHR')
            ->assertSee('SMS fallback')
            ->assertSee(route('get-started'), false);
    }

    public function test_get_started_page_renders_public_deployment_choices(): void
    {
        $response = $this->get(route('get-started'));

        $response
            ->assertOk()
            ->assertSee('Sign in or request a BorderReach workspace')
            ->assertSee('Managed BorderReach workspace')
            ->assertSee('Private government deployment')
            ->assertSee('Request a workspace')
            ->assertSee('Request deployment review')
            ->assertSee('Tell us about the deployment')
            ->assertSee(route('login'), false);
    }
}
