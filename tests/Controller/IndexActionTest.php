<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class IndexActionTest extends WebTestCase
{
    private const APP_NAME = 'symfony-skeleton';
    private const APP_VERSION = 'DEV';

    public function testActionReturnsResponse(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');

        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');

        $responseData = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'appName' => self::APP_NAME,
            'appVersion' => self::APP_VERSION,
            'apiDocs' => 'http://localhost/docs/swagger.yaml',
        ], $responseData);
    }
}
