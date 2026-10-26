<?php

namespace Tests\Feature\Securite;

use App\Console\Commands\GenererPermissions;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** La configuration chargée par l'application est identique à specs/securite/permissions.yaml (MKY-P1-05). */
class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_configuration_est_identique_au_yaml(): void
    {
        $this->assertSame(GenererPermissions::configurationDepuisYaml(), config('permissions'));
        $this->artisan('makary:permissions', ['--verifier' => true])->assertSuccessful();
    }

    public function test_roles_et_trente_cinq_permissions(): void
    {
        $this->assertCount(9, config('permissions.roles'));
        $this->assertCount(35, config('permissions.permissions'));
        foreach (config('permissions.attributions') as $role => $attributions) {
            $this->assertArrayHasKey($role, config('permissions.roles'));
            foreach ($attributions as $permission => $portee) {
                $this->assertArrayHasKey($permission, config('permissions.permissions'), "$role : $permission");
                $this->assertContains($portee, ['toutes', 'propre', 'perimetre']);
            }
        }
    }

    public function test_la_table_des_roles_reprend_l_exigence_de_double_authentification(): void
    {
        $attendu = array_map(fn (array $role) => $role['mfa'], config('permissions.roles'));
        $enBase = Role::pluck('mfa_requise', 'code')->all();
        ksort($attendu);
        ksort($enBase);
        $this->assertSame($attendu, $enBase);
    }

    public function test_chaque_permission_est_une_gate(): void
    {
        foreach (array_keys(config('permissions.permissions')) as $permission) {
            $this->assertTrue(Gate::has($permission), $permission);
        }
    }
}
