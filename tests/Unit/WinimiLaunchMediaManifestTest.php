<?php

namespace Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;

class WinimiLaunchMediaManifestTest extends TestCase
{
    /**
     * @throws JsonException
     */
    private function manifest(): array
    {
        $path = dirname(__DIR__, 2)
            .'/database/data/winimi-launch-media-manifest-v1.json';

        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);

        return json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function test_launch_manifest_contains_exactly_five_representative_products(): void
    {
        $manifest = $this->manifest();

        $products = $manifest['products'];

        $this->assertCount(5, $products);

        $codes = array_column(
            $products,
            'productCode',
        );

        sort($codes);

        $expected = [
            'VIN-CRL-024',
            'VIN-CW-001',
            'VIN-MV-008',
            'VIN-SS-015',
            'VIN-WP-027',
        ];

        sort($expected);

        $this->assertSame(
            $expected,
            $codes,
        );

        $this->assertNotContains(
            'VIN-VWC-028',
            $codes,
        );
    }

    public function test_every_launch_product_has_exactly_one_main_image(): void
    {
        foreach (
            $this->manifest()['products'] as $product
        ) {
            $mainImages = array_filter(
                $product['images'],
                static fn (array $image): bool => in_array(
                    'product_main',
                    $image['roles'],
                    true,
                ),
            );

            $this->assertCount(
                1,
                $mainImages,
                $product['productCode']
                    .' must have exactly one main image.',
            );
        }
    }

    public function test_all_selected_product_identities_are_confirmed(): void
    {
        foreach (
            $this->manifest()['products'] as $product
        ) {
            $this->assertStringStartsWith(
                'identity_confirmed',
                $product[
                    'verificationStatus'
                ],
            );

            $this->assertTrue(
                $product['launchEnabled'],
            );
        }
    }

    public function test_gift_box_is_explicitly_disabled_for_initial_launch(): void
    {
        $manifest = $this->manifest();

        $this->assertFalse(
            $manifest[
                'launchPolicy'
            ][
                'giftBoxLaunchEnabled'
            ],
        );

        $gift = array_values(
            array_filter(
                $manifest[
                    'excludedFromInitialLaunch'
                ],
                static fn (array $item): bool => ($item['name'] ?? null)
                        === 'باکس هدیه وینیمی',
            ),
        );

        $this->assertCount(1, $gift);

        $this->assertFalse(
            $gift[0]['launchEnabled'],
        );
    }

    public function test_roll_composition_matches_confirmed_source(): void
    {
        $products = array_column(
            $this->manifest()['products'],
            null,
            'productCode',
        );

        $this->assertSame(
            [
                'گردو',
                'شکلات',
                'کرمفیل وانیلی',
            ],
            $products[
                'VIN-CRL-024'
            ][
                'confirmedComposition'
            ],
        );
    }
}
