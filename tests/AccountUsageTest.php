<?php

namespace ThijsDeZoete\TinifyStatamic\Tests;

use Illuminate\Support\Facades\Gate;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use Statamic\Facades\User;
use Tinify\Client as SdkClient;
use Tinify\ConnectionException;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use Tinify\Tinify;

class AccountUsageTest extends TestCase
{
    protected function tearDown(): void
    {
        Tinify::setKey(null);
        Tinify::setCompressionCount(null);

        parent::tearDown();
    }

    public function test_account_usage_requires_addon_settings_permission(): void
    {
        $this->actingAs(User::make()->id('reader')->email('reader@example.test'));
        Gate::before(fn ($user, $ability) => $ability === 'access cp' ? true : null);
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('accountUsage');
        $this->app->instance(Client::class, $client);

        $this->getJson(cp_route('tinify.account-usage'))->assertForbidden();
    }

    public function test_assigned_balance_is_reported_separately_from_monthly_usage(): void
    {
        $this->actingAs(User::make()->id('admin')->email('admin@example.test')->makeSuper());
        $this->fakeSdk()->shouldReceive('request')->with('get', '/keys/offline-test-key')
            ->andReturn((object) ['headers' => [
                'compression-count' => '2',
                'compression-count-remaining' => '98063',
                'email-address' => 'private@example.test',
            ]]);

        $this->getJson(cp_route('tinify.account-usage'))->assertOk()->assertExactJson([
            'keyValid' => true,
            'compressionCount' => 2,
            'remainingCredits' => 98063,
        ]);
    }

    public function test_zero_balances_are_available_but_a_failed_refresh_cannot_reuse_them(): void
    {
        $this->actingAs(User::make()->id('admin')->email('admin@example.test')->makeSuper());
        $sdk = $this->fakeSdk();
        $sdk->shouldReceive('request')->once()->ordered()->andReturn((object) ['headers' => [
            'compression-count' => '0',
            'compression-count-remaining' => '0',
        ]]);
        $sdk->shouldReceive('request')->once()->ordered()
            ->andThrow(new ConnectionException('Sensitive upstream failure details.'));

        $this->getJson(cp_route('tinify.account-usage'))->assertOk()
            ->assertExactJson(['keyValid' => true, 'compressionCount' => 0, 'remainingCredits' => 0]);
        $this->getJson(cp_route('tinify.account-usage'))->assertOk()
            ->assertExactJson(['keyValid' => false, 'compressionCount' => null, 'remainingCredits' => null]);
    }

    public function test_missing_balance_is_not_inferred_from_monthly_usage(): void
    {
        $this->actingAs(User::make()->id('admin')->email('admin@example.test')->makeSuper());
        $this->fakeSdk()->shouldReceive('request')->andReturn((object) ['headers' => [
            'compression-count' => '2',
        ]]);

        $this->getJson(cp_route('tinify.account-usage'))->assertOk()
            ->assertExactJson(['keyValid' => true, 'compressionCount' => 2, 'remainingCredits' => null]);
    }

    public function test_missing_key_does_not_display_a_previous_accounts_usage(): void
    {
        $this->actingAs(User::make()->id('admin')->email('admin@example.test')->makeSuper());
        $this->fakeSdk()->shouldNotReceive('request');
        Tinify::setCompressionCount(1234);
        config(['tinify.key' => null]);
        $this->configureSettings(['api_key' => '']);

        $this->getJson(cp_route('tinify.account-usage'))->assertOk()
            ->assertExactJson(['keyValid' => false, 'compressionCount' => null, 'remainingCredits' => null]);
    }

    private function fakeSdk(): MockInterface
    {
        // Configure the SDK before installing its process-global fake.
        (new ReflectionMethod(Client::class, 'initialize'))->invoke(app(Client::class));
        $sdk = Mockery::mock(SdkClient::class);
        Tinify::setClient($sdk);

        return $sdk;
    }
}
