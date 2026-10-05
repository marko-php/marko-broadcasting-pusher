<?php

declare(strict_types=1);

use Marko\Broadcasting\Pusher\Auth\PusherSignature;
use Marko\Broadcasting\Pusher\PusherConfig;

function pusherSpecSignature(): PusherSignature
{
    // Credentials from the published Pusher HTTP API and channel authorization examples.
    return new PusherSignature(new PusherConfig(
        appId: '3',
        key: '278d425bdf160c739803',
        secret: '7ad3773142a6692b25b8',
    ));
}

describe('PusherSignature', function (): void {
    it('signs the published Pusher REST example', function (): void {
        $body = '{"name":"foo","channels":["project-3"],"data":"{\"some\":\"data\"}"}';

        $query = pusherSpecSignature()->signedQuery('POST', '/apps/3/events', $body, 1353088179);

        expect($query)->toBe([
            'auth_key' => '278d425bdf160c739803',
            'auth_timestamp' => '1353088179',
            'auth_version' => '1.0',
            'body_md5' => 'ec365a775a4cd0599faeb73354201b6f',
            'auth_signature' => 'da454824c97ba181a32ccc17a72625ba02771f50b50e1e7430e47a1f3f457e6c',
        ]);
    });

    it('sorts query parameters before signing', function (): void {
        $signature = pusherSpecSignature();

        expect($signature->sign('GET', '/apps/3/channels', ['b' => '2', 'a' => '1']))
            ->toBe(hash_hmac('sha256', "GET\n/apps/3/channels\na=1&b=2", '7ad3773142a6692b25b8'));
    });

    it('lowercases query parameter names before signing', function (): void {
        $signature = pusherSpecSignature();

        expect($signature->sign('GET', '/apps/3/channels', ['Filter_By_Prefix' => 'presence-']))
            ->toBe($signature->sign('GET', '/apps/3/channels', ['filter_by_prefix' => 'presence-']));
    });

    it('signs the published Pusher channel auth example', function (): void {
        expect(pusherSpecSignature()->channelAuth('1234.1234', 'private-foobar'))
            ->toBe('278d425bdf160c739803:58df8b0c36d6982b82c3ecf6b4662e34fe8c25bba48f5369f135bf843651c3a4');
    });
});
