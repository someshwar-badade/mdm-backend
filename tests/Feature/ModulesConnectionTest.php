<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModulesConnectionTest extends TestCase
{
    /**
     * Verify that all 9 modules have registered routes and are responsive.
     */
    public function test_modules_are_booted_and_responsive(): void
    {
        $modules = [
            'identity',
            'tenancy',
            'organizations',
            'devices',
            'enrollment',
            'policies',
            'commands',
            'audit',
            'notifications'
        ];

        foreach ($modules as $module) {
            $response = $this->getJson("/api/v1/{$module}/health");

            $response->assertStatus(200)
                ->assertJson([
                    'status' => 'ok',
                    'module' => ucfirst($module),
                ]);
        }
    }
}
