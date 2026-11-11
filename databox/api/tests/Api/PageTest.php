<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Page\Page;
use App\Tests\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PageTest extends AbstractDataboxTestCase
{
    public static function blankSlugProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'null' => [null];
    }

    public static function unresolvableSlugProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['unknown'];
    }

    public function testPageCannotBeCreatedWithoutSlug(): void
    {
        $client = static::createClient();

        $client->request('POST', '/pages', [
            'headers' => self::adminHeaders(),
            'json' => ['title' => 'Landing'],
        ]);
        $this->assertSlugIsRejected();
    }

    #[DataProvider('blankSlugProvider')]
    public function testPageCannotBeCreatedWithBlankSlug(?string $slug): void
    {
        $client = static::createClient();

        $client->request('POST', '/pages', [
            'headers' => self::adminHeaders(),
            'json' => ['title' => 'Landing', 'slug' => $slug],
        ]);
        $this->assertSlugIsRejected();
    }

    #[DataProvider('blankSlugProvider')]
    public function testPageSlugCannotBeCleared(?string $slug): void
    {
        $client = static::createClient();
        $page = $this->createPage('about');

        $client->request('PUT', '/pages/'.$page->getId(), [
            'headers' => self::adminHeaders(),
            'json' => ['slug' => $slug],
        ]);
        $this->assertSlugIsRejected();
    }

    public function testPageBySlugIsResolved(): void
    {
        $client = static::createClient();
        $page = $this->createPage('about');

        $client->request('GET', '/page-by-slug/about');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $page->getId(),
            'slug' => 'about',
        ]);
    }

    #[DataProvider('unresolvableSlugProvider')]
    public function testPageBySlugIsNotFound(string $slug): void
    {
        $client = static::createClient();
        $this->createPage('about');

        $client->request('GET', '/page-by-slug/'.$slug);
        $this->assertResponseStatusCodeSame(404);
    }

    private function assertSlugIsRejected(): void
    {
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [['propertyPath' => 'slug']],
        ]);
    }

    private static function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
        ];
    }

    private function createPage(string $slug): Page
    {
        $em = self::getEntityManager();

        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle(ucfirst($slug));
        $page->setPublic(true);
        $page->setEnabled(true);
        $page->setOwnerId(KeycloakClientTestMock::ADMIN_UID);

        $em->persist($page);
        $em->flush();

        return $page;
    }
}
