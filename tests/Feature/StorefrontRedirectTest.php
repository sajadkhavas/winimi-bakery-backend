<?php

namespace Tests\Feature;

use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorefrontRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_resolver_returns_safe_redirect_and_counts_hit(): void
    {
        $redirect = Redirect::create([
            'from_url' => '/old-cookie',
            'to_url' => '/products/new-cookie',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->getJson('/api/store/redirect?path='.urlencode('/old-cookie'))
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.location', '/products/new-cookie')
            ->assertJsonPath('data.statusCode', 301);

        $this->assertDatabaseHas('redirects', [
            'id' => $redirect->id,
            'hit_count' => 1,
        ]);
    }

    public function test_inactive_redirect_is_not_exposed_or_counted(): void
    {
        $redirect = Redirect::create([
            'from_url' => '/inactive-old',
            'to_url' => '/products',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $this->getJson('/api/store/redirect?path='.urlencode('/inactive-old'))
            ->assertOk()
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.statusCode', null);

        $this->assertDatabaseHas('redirects', [
            'id' => $redirect->id,
            'hit_count' => 0,
        ]);
    }

    public function test_redirect_chain_is_collapsed_to_final_internal_target(): void
    {
        $middle = Redirect::create([
            'from_url' => '/old-b',
            'to_url' => '/products/final-cookie',
            'status_code' => 308,
            'is_active' => true,
        ]);
        $first = Redirect::create([
            'from_url' => '/old-a',
            'to_url' => '/old-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->getJson('/api/store/redirect?path='.urlencode('/old-a'))
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.location', '/products/final-cookie')
            ->assertJsonPath('data.statusCode', 301);

        $this->assertDatabaseHas('redirects', [
            'id' => $first->id,
            'hit_count' => 1,
        ]);
        $this->assertDatabaseHas('redirects', [
            'id' => $middle->id,
            'hit_count' => 0,
        ]);
    }

    public function test_external_and_protocol_relative_targets_are_rejected(): void
    {
        foreach (['https://evil.example/path', '//evil.example/path'] as $target) {
            try {
                Redirect::create([
                    'from_url' => '/legacy-'.md5($target),
                    'to_url' => $target,
                    'status_code' => 301,
                    'is_active' => true,
                ]);
                $this->fail('Unsafe redirect target was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('to_url', $exception->errors());
            }
        }
    }

    public function test_self_and_two_node_loops_are_rejected(): void
    {
        try {
            Redirect::create([
                'from_url' => '/same',
                'to_url' => '/same',
                'status_code' => 301,
                'is_active' => true,
            ]);
            $this->fail('Self redirect was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to_url', $exception->errors());
        }

        Redirect::create([
            'from_url' => '/loop-a',
            'to_url' => '/loop-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        try {
            Redirect::create([
                'from_url' => '/loop-b',
                'to_url' => '/loop-a',
                'status_code' => 301,
                'is_active' => true,
            ]);
            $this->fail('Two-node redirect loop was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to_url', $exception->errors());
        }
    }

    public function test_invalid_status_and_protected_source_are_rejected(): void
    {
        try {
            Redirect::create([
                'from_url' => '/legacy-status',
                'to_url' => '/products',
                'status_code' => 200,
                'is_active' => true,
            ]);
            $this->fail('Invalid redirect status was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status_code', $exception->errors());
        }

        foreach (['/', '/admin', '/checkout/order', '/api/catalog/products'] as $source) {
            try {
                Redirect::create([
                    'from_url' => $source,
                    'to_url' => '/products',
                    'status_code' => 301,
                    'is_active' => true,
                ]);
                $this->fail('Protected redirect source was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('from_url', $exception->errors());
            }
        }
    }

    public function test_encoded_network_path_and_dot_segments_are_rejected(): void
    {
        foreach (['/%2F%2Fevil.example/path', '/safe/%2E%2E/admin'] as $target) {
            try {
                Redirect::create([
                    'from_url' => '/encoded-'.md5($target),
                    'to_url' => $target,
                    'status_code' => 301,
                    'is_active' => true,
                ]);
                $this->fail('Encoded unsafe target was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('to_url', $exception->errors());
            }
        }
    }

    public function test_runtime_fails_closed_for_legacy_unsafe_database_row(): void
    {
        DB::table('redirects')->insert([
            'from_url' => '/legacy-unsafe',
            'to_url' => 'https://evil.example/',
            'status_code' => 301,
            'is_active' => true,
            'hit_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/store/redirect?path='.urlencode('/legacy-unsafe'))
            ->assertOk()
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.location', null);

        $this->assertDatabaseHas('redirects', [
            'from_url' => '/legacy-unsafe',
            'hit_count' => 0,
        ]);
    }
}
