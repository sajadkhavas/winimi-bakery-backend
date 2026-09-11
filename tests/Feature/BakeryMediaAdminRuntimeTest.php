<?php

namespace Tests\Feature;

use App\Filament\Resources\BakeryMediaAssetResource;
use App\Filament\Resources\BakeryMediaAssetResource\Pages\ManageBakeryMediaAssets;
use App\Models\BakeryMediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BakeryMediaAdminRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_media_create_requires_a_source_image_and_never_creates_an_orphan_record(): void
    {
        $this->actingAs($this->operator());

        Livewire::test(ManageBakeryMediaAssets::class)
            ->callAction('create', data: [
                'title' => 'رسانه بدون فایل',
                'usage' => BakeryMediaAsset::USAGE_UNASSIGNED,
                'status' => BakeryMediaAsset::STATUS_PENDING,
            ])
            ->assertHasActionErrors(['source_image' => 'required']);

        $this->assertDatabaseCount('bakery_media_assets', 0);
    }

    public function test_admin_upload_limit_uses_the_same_spatie_limit_and_allows_a_five_megabyte_file(): void
    {
        $configuredBytes = (int) config('media-library.max_file_size');
        $resourceKilobytes = BakeryMediaAssetResource::maxUploadSizeKilobytes();

        $this->assertSame((int) floor($configuredBytes / 1024), $resourceKilobytes);
        $this->assertGreaterThanOrEqual(5 * 1024, $resourceKilobytes);
    }

    private function operator(): User
    {
        Role::findOrCreate('super_admin', 'web');

        $user = User::query()->create([
            'name' => 'Media Runtime Admin',
            'email' => 'media-runtime-admin@example.test',
            'password' => 'media-runtime-test-password',
        ]);
        $user->assignRole('super_admin');

        return $user;
    }
}
