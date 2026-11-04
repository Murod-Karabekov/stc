<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomepageLoadsSuccessfullyAnonymously(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'JDPU STC');
        self::assertSelectorExists('canvas[data-homepage-target="particleCanvas"]');
        self::assertSelectorExists('canvas[data-homepage-target="waveformCanvas"]');
        self::assertSelectorExists('button[data-action="click->homepage#startExperience"]');
        self::assertSelectorTextContains('button[data-action="click->homepage#startExperience"]', 'Boshlash');
        self::assertSelectorTextContains('p', 'Ovoz bilan tanishing');
        self::assertSelectorExists('audio[data-homepage-target="audio"]');
    }

    public function testPortraitAssetIsPresentAndAccessible(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $img = $crawler->filter('img[data-homepage-target="portrait"]');
        self::assertCount(1, $img);
        self::assertStringContainsString('murod_portrait.jpg', (string) $img->attr('src'));
    }
}
