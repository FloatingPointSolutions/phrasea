<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Page\Page;
use App\Tests\AbstractDataboxTestCase;

class PageTest extends AbstractDataboxTestCase
{
    public function testPageCannotBeCreatedWithoutSlug(): void
    {
        $client = static::createClient();

        $client->request('POST', '/pages', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Landing',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testPageCannotBeCreatedWithEmptySlug(): void
    {
        $client = static::createClient();

        $client->request('POST', '/pages', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Landing',
                'slug' => '',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
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

    public function testEmptySlugIsNotFound(): void
    {
        $client = static::createClient();
        $this->createPage('about');

        $client->request('GET', '/page-by-slug/');
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/page-by-slug/unknown');
        $this->assertResponseStatusCodeSame(404);
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
