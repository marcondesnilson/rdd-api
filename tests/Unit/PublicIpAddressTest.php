<?php

namespace Tests\Unit;

use App\Support\PublicIpAddress;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PublicIpAddressTest extends TestCase
{
    public function test_it_ignores_forwarded_headers_from_untrusted_origins(): void
    {
        $request = Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
        ]);

        $ip = (new PublicIpAddress)->fromRequest($request, ['10.0.1.0/24']);

        $this->assertSame('10.0.0.10', $ip);
    }

    public function test_it_prefers_cloudflare_header_from_trusted_origins(): void
    {
        $request = Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_CF_CONNECTING_IP' => '8.8.8.8',
            'HTTP_X_FORWARDED_FOR' => '1.1.1.1',
        ]);

        $ip = (new PublicIpAddress)->fromRequest($request, ['10.0.0.0/24']);

        $this->assertSame('8.8.8.8', $ip);
    }

    public function test_it_extracts_first_public_ip_from_x_forwarded_for(): void
    {
        $request = Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '10.1.0.1, 192.168.1.20, 1.1.1.1, 8.8.8.8',
        ]);

        $ip = (new PublicIpAddress)->fromRequest($request, ['10.0.0.10']);

        $this->assertSame('1.1.1.1', $ip);
    }
}
