<?php

namespace Tests\Unit;

use App\Services\Content\SsrfException;
use App\Services\Content\SsrfGuard;
use PHPUnit\Framework\TestCase;

class SsrfGuardTest extends TestCase
{
    private SsrfGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new SsrfGuard;
    }

    public function test_blocks_aws_metadata_endpoint(): void
    {
        $this->expectException(SsrfException::class);
        $this->guard->validate('http://169.254.169.254/latest/meta-data/');
    }

    public function test_blocks_localhost_ip(): void
    {
        $this->expectException(SsrfException::class);
        $this->guard->validate('http://127.0.0.1/');
    }

    public function test_blocks_private_ranges(): void
    {
        foreach (['http://10.0.0.1/', 'http://192.168.1.1/', 'http://172.16.0.1/'] as $url) {
            try {
                $this->guard->validate($url);
                $this->fail("Expected SsrfException for {$url}");
            } catch (SsrfException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_blocks_non_http_schemes(): void
    {
        foreach (['ftp://example.com/', 'file:///etc/passwd', 'gopher://example.com/'] as $url) {
            try {
                $this->guard->validate($url);
                $this->fail("Expected SsrfException for {$url}");
            } catch (SsrfException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_allows_public_ip(): void
    {
        $result = $this->guard->validate('https://1.1.1.1/');
        $this->assertSame('1.1.1.1', $result['host']);
        $this->assertSame('https', $result['scheme']);
    }
}
