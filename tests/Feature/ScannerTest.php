<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    /**
     * Test polling scanner when session is empty.
     */
    public function test_poll_returns_empty_when_no_sku_scanned(): void
    {
        $response = $this->getJson(route('api.scanner.poll', ['session' => 'non-existent-session']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'sku' => null,
            ]);
    }

    /**
     * Test barcode submission and subsequent poll.
     */
    public function test_submit_barcode_and_poll(): void
    {
        $session = 'test-session-id';
        $sku = '8991234567890';

        // 1. Submit the barcode
        $submitResponse = $this->postJson(route('api.scanner.submit'), [
            'session' => $session,
            'sku' => $sku,
        ]);

        $submitResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Barcode berhasil terkirim ke POS.',
            ]);

        // Verify it was stored in Cache
        $this->assertTrue(Cache::has("scanner_session_{$session}"));
        $this->assertEquals($sku, Cache::get("scanner_session_{$session}"));

        // 2. Poll the session (should return the SKU and clear it from Cache)
        $pollResponse = $this->getJson(route('api.scanner.poll', ['session' => $session]));

        $pollResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sku' => $sku,
            ]);

        // Verify Cache is cleared
        $this->assertFalse(Cache::has("scanner_session_{$session}"));

        // 3. Second poll should return empty
        $secondPollResponse = $this->getJson(route('api.scanner.poll', ['session' => $session]));
        $secondPollResponse->assertStatus(200)
            ->assertJson([
                'success' => false,
                'sku' => null,
            ]);
    }

    /**
     * Test submission validation.
     */
    public function test_submit_requires_session_and_sku(): void
    {
        $response = $this->postJson(route('api.scanner.submit'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session', 'sku']);
    }
}
