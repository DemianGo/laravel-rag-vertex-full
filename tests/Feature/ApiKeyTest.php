<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserPlan;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user with plan
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        UserPlan::create([
            'user_id' => $this->user->id,
            'plan' => 'free',
            'tokens_used' => 0,
            'tokens_limit' => 100,
            'documents_used' => 0,
            'documents_limit' => 1,
        ]);
    }

    public function test_user_can_generate_api_key()
    {
        $this->assertNull($this->user->api_key);
        $this->assertFalse($this->user->hasApiKey());

        $apiKey = $this->user->generateApiKey();

        $this->assertNotNull($apiKey);
        $this->assertTrue(str_starts_with($apiKey, 'rag_'));
        $this->assertEquals(60, strlen($apiKey));
        $this->assertTrue($this->user->fresh()->hasApiKey());
    }

    public function test_user_can_regenerate_api_key()
    {
        $firstKey = $this->user->generateApiKey();
        sleep(1); // Ensure different timestamp
        
        $secondKey = $this->user->regenerateApiKey();

        $this->assertNotEquals($firstKey, $secondKey);
        $this->assertEquals($secondKey, $this->user->fresh()->api_key);
    }

    public function test_masked_api_key_is_displayed_correctly()
    {
        $apiKey = $this->user->generateApiKey();
        $maskedKey = $this->user->fresh()->masked_api_key;

        $this->assertStringStartsWith(substr($apiKey, 0, 12), $maskedKey);
        $this->assertStringContainsString('...', $maskedKey);
        $this->assertStringEndsWith(substr($apiKey, -4), $maskedKey);
    }

    public function test_api_key_authentication_with_valid_key()
    {
        $apiKey = $this->user->generateApiKey();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->getJson('/api/auth/test');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'API key is valid!',
            ])
            ->assertJsonPath('user.id', $this->user->id)
            ->assertJsonPath('user.email', $this->user->email);
    }

    public function test_api_key_authentication_with_x_api_key_header()
    {
        $apiKey = $this->user->generateApiKey();

        $response = $this->withHeaders([
            'X-API-Key' => $apiKey,
        ])->getJson('/api/auth/test');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_api_key_authentication_fails_without_key()
    {
        $response = $this->getJson('/api/auth/test');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'API key required',
            ]);
    }

    public function test_api_key_authentication_fails_with_invalid_key()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_key_12345',
        ])->getJson('/api/auth/test');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid API key',
            ]);
    }

    public function test_api_key_last_used_timestamp_is_updated()
    {
        $apiKey = $this->user->generateApiKey();
        $this->assertNull($this->user->fresh()->api_key_last_used_at);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->getJson('/api/auth/test');

        $this->assertNotNull($this->user->fresh()->api_key_last_used_at);
    }

    public function test_user_plan_is_included_in_auth_test_response()
    {
        $apiKey = $this->user->generateApiKey();
        
        // Ensure userPlan relationship is loaded
        $this->user->load('userPlan');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->getJson('/api/auth/test');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'user' => ['id', 'name', 'email'],
                'plan' => ['plan', 'tokens_used', 'tokens_limit', 'documents_used', 'documents_limit'],
            ])
            ->assertJsonPath('plan.plan', 'free')
            ->assertJsonPath('plan.tokens_limit', 100);
    }

    public function test_api_key_is_hidden_in_json_serialization()
    {
        $apiKey = $this->user->generateApiKey();
        $userArray = $this->user->fresh()->toArray();

        $this->assertArrayNotHasKey('api_key', $userArray);
    }

    public function test_api_key_can_be_revoked()
    {
        $this->user->generateApiKey();
        $this->assertTrue($this->user->hasApiKey());

        $this->user->api_key = null;
        $this->user->api_key_created_at = null;
        $this->user->api_key_last_used_at = null;
        $this->user->save();

        $this->assertFalse($this->user->hasApiKey());
    }

    public function test_multiple_users_can_have_different_api_keys()
    {
        $user1 = $this->user;
        $user2 = User::factory()->create();

        $apiKey1 = $user1->generateApiKey();
        $apiKey2 = $user2->generateApiKey();

        $this->assertNotEquals($apiKey1, $apiKey2);

        // Test both keys work independently
        $response1 = $this->withHeaders(['Authorization' => 'Bearer ' . $apiKey1])
            ->getJson('/api/auth/test');
        $response2 = $this->withHeaders(['Authorization' => 'Bearer ' . $apiKey2])
            ->getJson('/api/auth/test');

        $response1->assertStatus(200)->assertJsonPath('user.id', $user1->id);
        $response2->assertStatus(200)->assertJsonPath('user.id', $user2->id);
    }
}
