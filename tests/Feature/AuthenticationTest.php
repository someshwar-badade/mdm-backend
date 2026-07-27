<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Organizations\Domain\Entities\Organization;
use Modules\Identity\Domain\Entities\User;
use Modules\Identity\Domain\Entities\RefreshToken;
use Modules\Enrollment\Domain\Entities\EnrollmentToken;
use Modules\Devices\Domain\Entities\Device;
use Modules\Tenancy\Application\Services\TenantContext;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    /**
     * Test admin portal login and token retrieval.
     */
    public function test_admin_login(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);

        // Login with invalid credentials
        $response = $this->postJson('/api/v1/identity/auth/login', [
            'email' => 'admin@mdm.com',
            'password' => 'wrongpass'
        ]);
        $response->assertStatus(401);

        // Login with valid credentials
        $response = $this->postJson('/api/v1/identity/auth/login', [
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'expires_in'
            ]);

        $accessToken = $response->json('access_token');

        // Test GET /me with access token
        $meResponse = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('/api/v1/identity/auth/me');
        $meResponse->assertStatus(200)
            ->assertJson([
                'email' => 'admin@mdm.com'
            ]);
    }

    /**
     * Test refresh token rotation and reuse detection.
     */
    public function test_refresh_token_rotation_and_reuse_detection(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);

        // 1. Initial login to get refresh token
        $loginRes = $this->postJson('/api/v1/identity/auth/login', [
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);
        $refreshToken1 = $loginRes->json('refresh_token');

        // 2. Refresh tokens using refresh token
        $refreshRes = $this->postJson('/api/v1/identity/auth/refresh', [
            'refresh_token' => $refreshToken1
        ]);
        $refreshRes->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $refreshToken2 = $refreshRes->json('refresh_token');

        // Confirm old refresh token is marked as revoked/used
        $this->assertTrue(RefreshToken::where('token', $refreshToken1)->first()->is_revoked);

        // 3. Attempt to REUSE the old refresh token (simulate theft)
        $reuseRes = $this->postJson('/api/v1/identity/auth/refresh', [
            'refresh_token' => $refreshToken1
        ]);
        $reuseRes->assertStatus(401)
            ->assertJsonFragment([
                'message' => 'Security alert: Refresh token has already been used or revoked. All active sessions terminated.'
            ]);

        // Verify that the NEW refresh token was also revoked as a security measure
        $this->assertTrue(RefreshToken::where('token', $refreshToken2)->first()->is_revoked);
    }

    /**
     * Test refresh token revocation on logout.
     */
    public function test_admin_logout_revocation(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);

        $loginRes = $this->postJson('/api/v1/identity/auth/login', [
            'email' => 'admin@mdm.com',
            'password' => 'secret123'
        ]);
        $refreshToken = $loginRes->json('refresh_token');

        // Logout
        $logoutRes = $this->postJson('/api/v1/identity/auth/logout', [
            'refresh_token' => $refreshToken
        ]);
        $logoutRes->assertStatus(200);

        // Verify token is revoked
        $this->assertTrue(RefreshToken::where('token', $refreshToken)->first()->is_revoked);

        // Try to refresh with revoked token -> should fail
        $refreshRes = $this->postJson('/api/v1/identity/auth/refresh', [
            'refresh_token' => $refreshToken
        ]);
        $refreshRes->assertStatus(401);
    }

}
