<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Proud\Location\LocationAddress;

/**
 * Tests for LocationAddress::apply_geocode() and related helpers.
 *
 * All geocode logic is exercised through apply_geocode( int $post_id, array
 * $values ): array, which takes validated $values and returns the final
 * $values with lat/lng resolved. This avoids touching FormHelper or $_POST.
 *
 * Issue: https://github.com/proudcity/wp-proudcity/issues/2840
 */
class LocationAddressSaveMetaTest extends TestCase
{
    /** @var LocationAddress */
    private LocationAddress $subject;

    /** A mock Google geocode response body that returns a lat/lng pair. */
    private string $successBody;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // LocationAddress constructor calls add_action / add_meta_box via
        // ProudMetaBox::__construct() — both are already stubbed. Also calls
        // set_fields(false) inside register_box(), which we do not want to
        // trigger here because it references __pcHelp. Skip that entirely by
        // not calling register_box(). We just need the object.
        $this->subject = new LocationAddress();

        $this->successBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => 34.0736204,
                            'lng' => -117.6479649,
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helper: build a minimal $values array
    // -----------------------------------------------------------------------

    private function values(array $overrides = []): array
    {
        return array_merge([
            'address'      => '100 Civic Center Dr',
            'address2'     => '',
            'city'         => 'Montclair',
            'state'        => 'CA',
            'zip'          => '91763',
            'custom_latlng' => '',
            'lat'          => '34.07',
            'lng'          => '-117.64',
        ], $overrides);
    }

    /** Stub get_post_meta so every field returns the given prior value. */
    private function stubPriorMeta(array $meta): void
    {
        Functions\when('get_post_meta')->alias(function (int $post_id, string $key, bool $single) use ($meta) {
            return $meta[$key] ?? '';
        });
    }

    /** Stub a successful Google geocode response. */
    private function stubGeoSuccess(?string $body = null): void
    {
        $body = $body ?? $this->successBody;
        Functions\when('wp_remote_get')->justReturn(['body' => $body, 'response' => ['code' => 200]]);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn($body);
        Functions\when('is_wp_error')->justReturn(false);
    }

    /** Stub a WP_Error response from wp_remote_get. */
    private function stubGeoError(): void
    {
        $err = new WP_Error('http_request_failed', 'cURL error');
        Functions\when('wp_remote_get')->justReturn($err);
        Functions\when('is_wp_error')->justReturn(true);
    }

    // -----------------------------------------------------------------------
    // Cleanup bug regressions
    // -----------------------------------------------------------------------

    /**
     * apply_geocode() must not produce any output (removes leftover print_r).
     */
    public function test_print_r_debug_output_removed(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '999 Old St',
            'address2'      => '',
            'city'          => 'Oldtown',
            'state'         => 'CA',
            'zip'           => '90000',
            'custom_latlng' => '',
            'lat'           => '34.0',
            'lng'           => '-117.0',
        ]);
        $this->stubGeoSuccess();

        ob_start();
        $this->subject->apply_geocode(42, $this->values(['address' => '200 New Ave']));
        $output = ob_get_clean();

        $this->assertSame('', $output, 'apply_geocode() must not echo anything (print_r removed).');
    }

    /**
     * The URL passed to wp_remote_get must include key=<api_key>.
     */
    public function test_geocode_url_includes_api_key_param(): void
    {
        Functions\when('get_option')->justReturn('TEST_KEY_xyz');
        $this->stubPriorMeta([
            'address'       => '999 Old St',
            'address2'      => '',
            'city'          => 'Oldtown',
            'state'         => 'CA',
            'zip'           => '90000',
            'custom_latlng' => '',
            'lat'           => '',
            'lng'           => '',
        ]);

        $capturedUrl = null;
        Functions\when('wp_remote_get')->alias(function (string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return new WP_Error('stub', 'captured');
        });
        Functions\when('wp_remote_retrieve_response_code')->justReturn(0);
        Functions\when('wp_remote_retrieve_body')->justReturn('');
        Functions\when('is_wp_error')->justReturn(true);

        // No prior lat/lng so geocode is attempted.
        $this->subject->apply_geocode(42, $this->values(['lat' => '', 'lng' => '']));

        $this->assertNotNull($capturedUrl, 'wp_remote_get must be called when lat/lng are empty.');
        $this->assertStringContainsString('key=TEST_KEY_xyz', $capturedUrl, 'Geocode URL must include key= parameter.');
    }

    /**
     * When lat is empty but lng is not, geocode must still run.
     * (Catches the duplicate-lat bug: empty($values['lat']) || empty($values['lat']).)
     */
    public function test_lat_empty_lng_populated_triggers_geocode(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Civic Center Dr',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '',
            'lng'           => '-117.64',
        ]);

        $called = false;
        Functions\when('wp_remote_get')->alias(function () use (&$called) {
            $called = true;
            return new WP_Error('stub', 'captured');
        });
        Functions\when('is_wp_error')->justReturn(true);

        $this->subject->apply_geocode(42, $this->values(['lat' => '', 'lng' => '-117.64']));

        $this->assertTrue($called, 'Geocode must run when lat is empty, even when lng is populated.');
    }

    /**
     * When lng is empty but lat is not, geocode must still run.
     */
    public function test_lng_empty_lat_populated_triggers_geocode(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Civic Center Dr',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '34.07',
            'lng'           => '',
        ]);

        $called = false;
        Functions\when('wp_remote_get')->alias(function () use (&$called) {
            $called = true;
            return new WP_Error('stub', 'captured');
        });
        Functions\when('is_wp_error')->justReturn(true);

        $this->subject->apply_geocode(42, $this->values(['lat' => '34.07', 'lng' => '']));

        $this->assertTrue($called, 'Geocode must run when lng is empty, even when lat is populated.');
    }

    // -----------------------------------------------------------------------
    // Core behavior
    // -----------------------------------------------------------------------

    /**
     * When an address field changes, geocode runs and new coords are saved.
     */
    public function test_address_changed_triggers_geocode(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '34.0',
            'lng'           => '-117.0',
        ]);
        $this->stubGeoSuccess();

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '34.0',   // stale coords still in $values
            'lng'     => '-117.0',
        ]));

        $this->assertEqualsWithDelta(34.0736204, (float) $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-117.6479649, (float) $result['lng'], 0.0001);
    }

    /**
     * When address is unchanged and lat/lng are populated, geocode is skipped.
     */
    public function test_address_unchanged_skips_geocode(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Civic Center Dr',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '34.07',
            'lng'           => '-117.64',
        ]);

        $called = false;
        Functions\when('wp_remote_get')->alias(function () use (&$called) {
            $called = true;
            return new WP_Error('stub', 'not expected');
        });
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->subject->apply_geocode(42, $this->values());

        $this->assertFalse($called, 'wp_remote_get must not be called when address is unchanged.');
        $this->assertSame('34.07', $result['lat']);
        $this->assertSame('-117.64', $result['lng']);
    }

    /**
     * custom_latlng checked: user-entered coords are kept, geocode never runs.
     */
    public function test_custom_latlng_checked_preserves_user_coords(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Civic Center Dr',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '34.07',
            'lng'           => '-117.64',
        ]);

        $called = false;
        Functions\when('wp_remote_get')->alias(function () use (&$called) {
            $called = true;
            return new WP_Error('stub', 'not expected');
        });
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address'       => '200 New Ave',  // address changed but custom overrides
            'custom_latlng' => '1',
            'lat'           => '40.0',
            'lng'           => '-74.0',
        ]));

        $this->assertFalse($called, 'wp_remote_get must not be called when custom_latlng is checked.');
        $this->assertSame('40.0', $result['lat']);
        $this->assertSame('-74.0', $result['lng']);
    }

    /**
     * When custom_latlng flips from '1' to '', geocode runs even if address
     * text is unchanged.
     */
    public function test_custom_latlng_unchecked_after_being_set_forces_geocode(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Civic Center Dr',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '1',
            'lat'           => '40.0',
            'lng'           => '-74.0',
        ]);
        $this->stubGeoSuccess();

        $result = $this->subject->apply_geocode(42, $this->values([
            'custom_latlng' => '',   // unchecked
            'lat'           => '40.0',
            'lng'           => '-74.0',
        ]));

        // Coords must have changed to geocoded values, not the old custom ones.
        $this->assertEqualsWithDelta(34.0736204, (float) $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-117.6479649, (float) $result['lng'], 0.0001);
    }

    /**
     * On geocode failure (WP_Error), previous coords are preserved.
     */
    public function test_geocode_failure_preserves_previous_coords(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);
        $this->stubGeoError();

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertSame('37.7', $result['lat'], 'Previous lat must be preserved on WP_Error.');
        $this->assertSame('-122.4', $result['lng'], 'Previous lng must be preserved on WP_Error.');
    }

    /**
     * On non-OK Google status, previous coords are preserved.
     */
    public function test_geocode_non_ok_status_preserves_previous_coords(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);

        $deniedBody = json_encode(['status' => 'REQUEST_DENIED']);
        Functions\when('wp_remote_get')->justReturn(['body' => $deniedBody]);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn($deniedBody);
        Functions\when('is_wp_error')->justReturn(false);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertSame('37.7', $result['lat'], 'Previous lat must be preserved on REQUEST_DENIED.');
        $this->assertSame('-122.4', $result['lng'], 'Previous lng must be preserved on REQUEST_DENIED.');
    }

    /**
     * On non-200 HTTP response, previous coords are preserved.
     */
    public function test_geocode_non_200_http_preserves_previous_coords(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);

        Functions\when('wp_remote_get')->justReturn(['body' => '{}']);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(500);
        Functions\when('wp_remote_retrieve_body')->justReturn('{}');
        Functions\when('is_wp_error')->justReturn(false);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertSame('37.7', $result['lat'], 'Previous lat must be preserved on HTTP 500.');
        $this->assertSame('-122.4', $result['lng'], 'Previous lng must be preserved on HTTP 500.');
    }

    /**
     * Missing API key: geocode skipped, previous coords preserved.
     */
    public function test_missing_api_key_preserves_previous_coords(): void
    {
        Functions\when('get_option')->justReturn('');  // empty key
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);

        $called = false;
        Functions\when('wp_remote_get')->alias(function () use (&$called) {
            $called = true;
            return new WP_Error('stub', 'should not be called');
        });
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertFalse($called, 'wp_remote_get must not be called when API key is empty.');
        $this->assertSame('37.7', $result['lat']);
        $this->assertSame('-122.4', $result['lng']);
    }

    /**
     * New post (no prior meta): geocode runs.
     */
    public function test_new_post_with_no_prior_meta_geocodes(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        // No prior meta — all fields return empty string.
        Functions\when('get_post_meta')->justReturn('');
        $this->stubGeoSuccess();

        $result = $this->subject->apply_geocode(99, $this->values([
            'lat' => '',
            'lng' => '',
        ]));

        $this->assertEqualsWithDelta(34.0736204, (float) $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-117.6479649, (float) $result['lng'], 0.0001);
    }

    /**
     * Empty address fields with no prior meta: geocode skipped, coords stay
     * empty (acceptable for a brand-new post with no address yet).
     */
    public function test_empty_address_skips_geocode_and_blanks_coords_allowed(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        Functions\when('get_post_meta')->justReturn('');

        $called = false;
        Functions\when('wp_remote_get')->alias(function () use (&$called) {
            $called = true;
            return new WP_Error('stub', 'should not be called');
        });
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->subject->apply_geocode(99, $this->values([
            'address' => '',
            'city'    => '',
            'state'   => '',
            'zip'     => '',
            'lat'     => '',
            'lng'     => '',
        ]));

        $this->assertFalse($called, 'wp_remote_get must not be called when address is empty.');
        $this->assertSame('', $result['lat']);
        $this->assertSame('', $result['lng']);
    }

    // -----------------------------------------------------------------------
    // address_string() bug: line 297 uses $location['address'] twice instead
    // of $location['address2']
    // -----------------------------------------------------------------------

    /**
     * address_string() must include address2 in the result, not address twice.
     */
    public function test_address_string_uses_address2_not_duplicate_address(): void
    {
        $location = [
            'address'  => '100 Civic Center Dr',
            'address2' => 'Suite 200',
            'city'     => 'Montclair',
            'state'    => 'CA',
            'zip'      => '91763',
        ];

        $result = $this->subject->address_string($location);

        $this->assertStringContainsString('Suite 200', $result, 'address2 must appear in address string.');
        // Should not have the address field duplicated in place of address2.
        $this->assertStringNotContainsString(
            '100 Civic Center Dr, 100 Civic Center Dr',
            $result,
            'address must not appear twice in address string.'
        );
    }

    // -----------------------------------------------------------------------
    // geocode_address() coordinate type validation
    // -----------------------------------------------------------------------

    /**
     * When the geocode response returns a non-numeric string for lat, the
     * geocode must be treated as a failure and previous coords preserved.
     */
    public function test_geocode_rejects_non_numeric_lat(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);

        $badBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => 'abc',
                            'lng' => -117.6479649,
                        ],
                    ],
                ],
            ],
        ]);
        $this->stubGeoSuccess($badBody);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertSame('37.7', $result['lat'], 'Non-numeric lat must be rejected; previous lat preserved.');
        $this->assertSame('-122.4', $result['lng'], 'Non-numeric lat must be rejected; previous lng preserved.');
    }

    /**
     * A lat value greater than 90 must be rejected and previous coords preserved.
     */
    public function test_geocode_rejects_out_of_range_lat(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);

        $badBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => 95.0,
                            'lng' => -117.6479649,
                        ],
                    ],
                ],
            ],
        ]);
        $this->stubGeoSuccess($badBody);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertSame('37.7', $result['lat'], 'Out-of-range lat (95) must be rejected; previous lat preserved.');
        $this->assertSame('-122.4', $result['lng'], 'Out-of-range lat (95) must be rejected; previous lng preserved.');
    }

    /**
     * A lng value less than -180 must be rejected and previous coords preserved.
     */
    public function test_geocode_rejects_out_of_range_lng(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);

        $badBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => 34.0736204,
                            'lng' => -200.0,
                        ],
                    ],
                ],
            ],
        ]);
        $this->stubGeoSuccess($badBody);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertSame('37.7', $result['lat'], 'Out-of-range lng (-200) must be rejected; previous lat preserved.');
        $this->assertSame('-122.4', $result['lng'], 'Out-of-range lng (-200) must be rejected; previous lng preserved.');
    }

    /**
     * lat=0, lng=0 is a valid coordinate (Gulf of Guinea) and must be accepted.
     */
    public function test_geocode_accepts_zero_coords(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        $this->stubPriorMeta([
            'address'       => '100 Old St',
            'address2'      => '',
            'city'          => 'Montclair',
            'state'         => 'CA',
            'zip'           => '91763',
            'custom_latlng' => '',
            'lat'           => '37.7',
            'lng'           => '-122.4',
        ]);

        $zeroBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => 0,
                            'lng' => 0,
                        ],
                    ],
                ],
            ],
        ]);
        $this->stubGeoSuccess($zeroBody);

        $result = $this->subject->apply_geocode(42, $this->values([
            'address' => '200 New Ave',
            'lat'     => '37.7',
            'lng'     => '-122.4',
        ]));

        $this->assertEqualsWithDelta(0.0, (float) $result['lat'], 0.0001, 'lat=0 must be accepted as a valid coordinate.');
        $this->assertEqualsWithDelta(0.0, (float) $result['lng'], 0.0001, 'lng=0 must be accepted as a valid coordinate.');
    }

    /**
     * When the API returns numeric strings (e.g. "34.07"), the stored values
     * must be PHP floats after geocoding.
     */
    public function test_geocode_stores_lat_lng_as_floats(): void
    {
        Functions\when('get_option')->justReturn('FAKE_KEY');
        Functions\when('get_post_meta')->justReturn('');

        $stringBody = json_encode([
            'status'  => 'OK',
            'results' => [
                [
                    'geometry' => [
                        'location' => [
                            'lat' => '34.07',
                            'lng' => '-117.64',
                        ],
                    ],
                ],
            ],
        ]);
        $this->stubGeoSuccess($stringBody);

        $result = $this->subject->apply_geocode(99, $this->values([
            'lat' => '',
            'lng' => '',
        ]));

        $this->assertIsFloat($result['lat'], 'Stored lat must be a PHP float, not a string.');
        $this->assertIsFloat($result['lng'], 'Stored lng must be a PHP float, not a string.');
    }

    // -----------------------------------------------------------------------
    // save_meta() autosave / revision guard
    // -----------------------------------------------------------------------

    /**
     * save_meta() must return early on autosave without calling save_all.
     */
    public function test_autosave_skips_save(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(false);

        // Simulate DOING_AUTOSAVE
        if (!defined('DOING_AUTOSAVE')) {
            define('DOING_AUTOSAVE', true);
        }

        $saved = false;
        Functions\when('update_post_meta')->alias(function () use (&$saved) {
            $saved = true;
        });

        $post = new stdClass();
        $post->post_type = 'proud_location';

        // save_meta() calls validate_values() which calls $this->form->getFormValues()
        // — form is null here so validate_values returns false. That is fine;
        // the guard must fire before we reach validate_values on autosave.
        $this->subject->save_meta(42, $post, true);

        $this->assertFalse($saved, 'update_post_meta must not be called during autosave.');
    }

    /**
     * save_meta() must return early for revisions.
     */
    public function test_revision_skips_save(): void
    {
        // Only run if DOING_AUTOSAVE is not already true from the previous test.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $this->markTestSkipped('DOING_AUTOSAVE already defined; revision test skipped in this process.');
        }

        Functions\when('wp_is_post_revision')->justReturn(5);  // truthy revision ID

        $saved = false;
        Functions\when('update_post_meta')->alias(function () use (&$saved) {
            $saved = true;
        });

        $post = new stdClass();
        $post->post_type = 'proud_location';

        $this->subject->save_meta(42, $post, true);

        $this->assertFalse($saved, 'update_post_meta must not be called for revisions.');
    }
}
