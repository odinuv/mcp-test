<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthCheckActionTest extends WebTestCase
{
    public function testActionReturnsResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health-check');

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');

        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');

        $responseData = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['status' => 'ok'], $responseData);
    }
}
