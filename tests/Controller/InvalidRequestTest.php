<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvalidRequestTest extends WebTestCase
{
    public function testNotFoundResponseIsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/aaa');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseFormatSame('json');

        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        self::assertResponseHeaderSame('Access-Control-Allow-Methods', '*');
        self::assertResponseHeaderSame('Access-Control-Allow-Headers', '*');

        $responseData = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($responseData);

        self::assertArrayHasKey('exceptionId', $responseData);
        self::assertIsString($responseData['exceptionId']);
        self::assertStringStartsWith('exception-', $responseData['exceptionId']);
        unset($responseData['exceptionId']);

        self::assertSame([
            'error' => 'No route found for "GET http://localhost/aaa"',
            'code' => 404,
            'status' => 'error',
            'context' => [],
        ], $responseData);
    }
}
