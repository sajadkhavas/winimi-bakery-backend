<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionContentSafetyTest extends TestCase
{
    public function test_production_deploy_is_code_only_by_default_and_blocks_pending_migrations(): void
    {
        $production = file_get_contents(base_path('deploy/bin/deploy-production-backend.sh'));
        $deploy = file_get_contents(base_path('deploy/bin/deploy-backend.sh'));

        $this->assertIsString($production);
        $this->assertIsString($deploy);

        $this->assertStringContainsString(
            'ALLOW_MIGRATIONS=${BACKEND_ALLOW_PRODUCTION_MIGRATIONS:-false}',
            $production,
        );
        $this->assertStringContainsString(
            'export BACKEND_REQUIRE_NO_PENDING_MIGRATIONS=true',
            $production,
        );
        $this->assertStringContainsString('php artisan migrate:status --no-interaction', $deploy);
        $this->assertStringContainsString('pending migrations exist', $deploy);
    }

    public function test_deploy_scripts_do_not_seed_or_reset_production_data(): void
    {
        foreach ([
            base_path('deploy/bin/deploy-production-backend.sh'),
            base_path('deploy/bin/deploy-backend.sh'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);

            foreach ([
                'db:seed',
                'migrate:fresh',
                'migrate:refresh',
                'migrate:reset',
                'schema:drop',
            ] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, $path);
            }
        }
    }
}
