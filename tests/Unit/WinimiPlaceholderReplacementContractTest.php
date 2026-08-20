<?php

namespace Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;

class WinimiPlaceholderReplacementContractTest extends TestCase
{
    /**
     * @throws JsonException
     */
    private function contract(): array
    {
        $path = dirname(__DIR__, 2)
            .'/database/data/winimi-production-placeholder-replacement-v1.json';

        $contents = file_get_contents(
            $path
        );

        $this->assertNotFalse(
            $contents
        );

        return json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function test_contract_contains_exactly_five_launch_products(): void
    {
        $contract = $this->contract();

        $this->assertSame(
            'production',
            $contract['environment'],
        );

        $this->assertCount(
            5,
            $contract['products'],
        );

        $codes = array_column(
            $contract['products'],
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
    }

    public function test_each_existing_main_has_strong_exact_identity(): void
    {
        foreach (
            $this->contract()['products'] as $product
        ) {
            $main = $product[
                'existingMain'
            ];

            $this->assertIsInt(
                $main['mediaId'],
            );

            $this->assertGreaterThan(
                0,
                $main['mediaId'],
            );

            $this->assertNotSame(
                '',
                trim(
                    $main['filename']
                ),
            );

            $this->assertContains(
                $main['mimeType'],
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
            );

            $this->assertGreaterThan(
                0,
                $main['sizeBytes'],
            );

            $this->assertMatchesRegularExpression(
                '/^[a-f0-9]{64}$/',
                $main['sha256'],
            );

            $this->assertTrue(
                $main[
                    'customPropertiesMustBeEmpty'
                ],
            );
        }
    }

    public function test_replacement_sources_match_launch_media_manifest_main_images(): void
    {
        $launchPath = dirname(__DIR__, 2)
            .'/database/data/winimi-launch-media-manifest-v1.json';

        $launch = json_decode(
            file_get_contents(
                $launchPath
            ),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $launchMain = [];

        foreach (
            $launch['products'] as $product
        ) {
            foreach (
                $product['images'] as $image
            ) {
                if (
                    in_array(
                        'product_main',
                        $image['roles'],
                        true,
                    )
                ) {
                    $launchMain[
                        $product[
                            'productCode'
                        ]
                    ] = $image[
                        'sourceFilename'
                    ];
                }
            }
        }

        foreach (
            $this->contract()['products'] as $product
        ) {
            $this->assertArrayHasKey(
                $product['productCode'],
                $launchMain,
            );

            $this->assertSame(
                $launchMain[
                    $product[
                        'productCode'
                    ]
                ],
                $product[
                    'replacementSource'
                ],
            );
        }
    }

    public function test_replacement_policy_is_fail_closed(): void
    {
        $policy =
            $this->contract()['policy'];

        $this->assertFalse(
            $policy[
                'automaticUnknownMediaDeletionAllowed'
            ],
        );

        $this->assertTrue(
            $policy[
                'explicitReplacementFlagRequired'
            ],
        );

        $this->assertTrue(
            $policy[
                'exactExistingMediaIdentityRequired'
            ],
        );

        $this->assertTrue(
            $policy[
                'existingOriginalMustBeBackedUpBeforeDeletion'
            ],
        );

        $this->assertTrue(
            $policy[
                'replacementFailureMustRestorePreviousMain'
            ],
        );

        $this->assertFalse(
            $policy[
                'mediaVerifiedAutoEnableAllowed'
            ],
        );

        $this->assertFalse(
            $policy[
                'galleryDeletionAllowed'
            ],
        );

        $this->assertFalse(
            $policy[
                'giftBoxIncluded'
            ],
        );
    }
}
