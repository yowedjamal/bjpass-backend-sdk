<?php

namespace BjPass\Tests;

use BjPass\BjPass;
use BjPass\Exceptions\BjPassException;
use Orchestra\Testbench\TestCase;

class BjPassTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \BjPass\Providers\BjPassServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('bjpass', [
            'base_url' => 'https://test-tx-pki.gouv.bj',
            'auth_server' => 'test-as',
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect_uri' => 'https://test.example.com/auth/callback',
            'scope' => 'openid profile',
        ]);
    }

    public function test_can_create_instance_with_valid_config()
    {
        $config = [
            'client_id' => 'test_client',
            'client_secret' => 'test_secret',
            'redirect_uri' => 'https://example.com/callback'
        ];

        $bjpass = new BjPass($config);
        
        $this->assertInstanceOf(BjPass::class, $bjpass);
        $this->assertEquals('test_client', $bjpass->getConfig()['client_id']);
    }

    public function test_throws_exception_with_missing_required_config()
    {
        $this->expectException(BjPassException::class);
        $this->expectExceptionMessage("Configuration field 'client_id' is required");

        new BjPass([]);
    }

    public function test_throws_exception_with_invalid_redirect_uri()
    {
        $this->expectException(BjPassException::class);
        $this->expectExceptionMessage("Invalid redirect_uri format");

        new BjPass([
            'client_id' => 'test_client',
            'client_secret' => 'test_secret',
            'redirect_uri' => 'invalid-uri'
        ]);
    }

    public function test_can_update_config()
    {
        $bjpass = new BjPass([
            'client_id' => 'test_client',
            'client_secret' => 'test_secret',
            'redirect_uri' => 'https://example.com/callback'
        ]);

        $newConfig = ['scope' => 'openid profile email'];
        $bjpass->updateConfig($newConfig);

        $this->assertEquals('openid profile email', $bjpass->getConfig()['scope']);
    }

    public function test_has_required_services()
    {
        $bjpass = new BjPass([
            'client_id' => 'test_client',
            'client_secret' => 'test_secret',
            'redirect_uri' => 'https://example.com/callback'
        ]);

        $this->assertNotNull($bjpass->getAuthService());
        $this->assertNotNull($bjpass->getTokenService());
        $this->assertNotNull($bjpass->getJwksService());
    }

    public function test_config_merging_works_correctly()
    {
        $bjpass = new BjPass([
            'client_id' => 'test_client',
            'client_secret' => 'test_secret',
            'redirect_uri' => 'https://example.com/callback',
            'base_url' => 'https://custom.example.com'
        ]);

        $config = $bjpass->getConfig();
        
        $this->assertEquals('https://custom.example.com', $config['base_url']);
        $this->assertEquals('main-as', $config['auth_server']); // valeur par défaut
        $this->assertEquals('openid profile', $config['scope']); // valeur par défaut
    }
}
