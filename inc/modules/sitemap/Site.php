<?php

/**
 * This file is part of Batflat ~ the lightweight, fast and easy CMS
 *
 * @author       Paweł Klockiewicz <klockiewicz@sruu.pl>
 * @author       Wojciech Król <krol@sruu.pl>
 * @copyright    2017 Paweł Klockiewicz, Wojciech Król <Sruu.pl>
 * @license      https://batflat.org/license
 * @link         https://batflat.org
 */

namespace Inc\Modules\Sitemap;

use Exception;
use Inc\Core\SiteModule;

class Site extends SiteModule
{
    /** @var array */
    private $urls = [];

    /** @var string */
    private $homepage = '';

    /** @var string */
    private $langSite = '';

    public function init()
    {
        $this->homepage = $this->settings('settings.homepage');
        $this->langSite = $this->settings('settings.lang_site');
        if ($this->settings('sitemap', 'noindex')) {
            $this->core->append('<meta name="robots" content="noindex" />', 'header');
        }
    }

    public function routes()
    {
        $this->route('sitemap.xml', function () {
            $this->setTemplate(false);
            header('Content-type: application/xml');

            // Home Page
            $this->urls[] = [
                'url' => url(),
                'lastmod' => null,
                'changefreq' => 'always'
            ];

            // Pages (including pages linked with a pagelist)
            $this->getPagesNodes();

            // Pages lists (not including pages linked on a pagelist)
            $this->getPagelistNodes();

            // Blog
            $this->getBlogNodes();

            echo '<?xml version="1.0" encoding="UTF-8"?>' .
                 $this->draw('sitemap.xml', ['urls' => $this->urls]);
        });
    }

    /**
     * Generate all nodes from pages module
     *
     * @throws Exception
     */
    private function getPagesNodes()
    {
        $pages = $this->db('pages')->asc('lang')->asc('id')->toArray();

        foreach ($pages as $page) {
            if (!file_exists(BASE_DIR . '/inc/lang/' . $page['lang'] . '/.lock')) {
                $page['date'] = strtotime($page['date']);

                $shortLang = strstr($page['lang'], '_', true);
                if (strpos($page['slug'], '404') !== false) {
                    continue;
                }

                if ($this->langSite == $page['lang'] && $this->homepage == $page['slug']) {
                    $this->urls[0]['lastmod'] = date('c', $page['date']);
                    $this->urls[0]['links'] = $this->getLinksSubNodes('pages', $page['slug']);
                } else {
                    if ($this->homepage == $page['slug']) {
                        // Not "main language" homepages
                        $this->urls[] = [
                            'url' => url($shortLang),
                            'links' => $this->getLinksSubNodes('pages', $page['slug']),
                            'lastmod' => date('c', $page['date']),
                            'changefreq' => 'always'
                        ];
                    } else {
                        if ($this->langSite == $page['lang']) {
                            $this->urls[] = [
                                'url' => url($page['slug']),
                                'links' => $this->getLinksSubNodes('pages', $page['slug']),
                                'lastmod' => date('c', $page['date']),
                                'changefreq' => 'monthly'
                            ];
                        } else {
                            $this->urls[] = [
                                'url' => url([$shortLang, $page['slug']]),
                                'links' => $this->getLinksSubNodes('pages', $page['slug']),
                                'lastmod' => date('c', $page['date']),
                                'changefreq' => 'monthly'
                            ];
                        }
                    }
                }
            }
        }
    }

    /**
     * Return an array including all links per language for one page given
     *
     * @param string $contentTableName
     * @param string $slug
     * @return array|null
     */
    private function getLinksSubNodes($contentTableName, $slug): ?array
    {
        $links = null;
        $contents = null;
        $contentAllowed = ['pages', 'pagelist', 'blog'];

        if (in_array($contentTableName, $contentAllowed)) {
            $contents = $this->db($contentTableName)->where('slug', $slug)->toArray();
        }

        foreach ($contents as $content) {
            $shortLang = strstr($content['lang'], '_', true);
            $url = url([$shortLang, $content['slug']]);
            if ($this->langSite == $content['lang']) {
                $url = url($content['slug']);
            }

            $links[] = [
                'hreflang' => $shortLang,
                'href' => $url
            ];
        }

        return $links;
    }

    /**
     * Generate all nodes from pagelist module
     *
     * @throws Exception
     */
    private function getPagelistNodes()
    {
        $pagelists = $this->db('pagelist')->toArray();

        // Pagelists URLs
        foreach ($pagelists as $pagelist) {
            if (!file_exists(BASE_DIR . '/inc/lang/' . $pagelist['lang'] . '/.lock')) {
                $this->urls[] = [
                    'url' => url($pagelist['slug']),
                    'links' => $this->getLinksSubNodes('pagelist', $pagelist['slug']),
                    'lastmod' => date('c', strtotime($pagelist['updated_at'])),
                    'changefreq' => 'never'
                ];
            }
        }
    }

    /**
     * Generate all nodes from blog module
     *
     * @throws Exception
     */
    private function getBlogNodes()
    {
        $blogBaseSlug = ltrim($this->settings('blog.slug'), '/');
        $posts = $this->db('blog')->where('status', 2)->desc('published_at')->toArray();
        $tags = $this->db('blog_tags_relationship')
            ->leftJoin('blog_tags', 'blog_tags.id = blog_tags_relationship.tag_id')
            ->leftJoin('blog', 'blog.id = blog_tags_relationship.blog_id')
            ->where('blog.status', 2)
            ->group('blog_tags.slug')
            ->select(['slug' => 'blog_tags.slug'])
            ->toArray();

        // Check if main blog page (with all results) is used as homepage
        if ($this->homepage != $blogBaseSlug) {
            $this->urls[] = [
                'url' => url($blogBaseSlug),
                'lastmod' => date('c', $posts[0]['published_at']),
                'changefreq' => 'daily'
            ];
        } else {
            $this->urls[0] = [
                'lastmod' => date('c', $posts[0]['published_at']),
                'changefreq' => 'daily'
            ];
        }
        // Posts URLs
        foreach ($posts as $post) {
            if (!file_exists(BASE_DIR . '/inc/lang/' . $post['lang'] . '/.lock')) {
                $imageUrl = url(MODULES . '/blog/img/default.jpg' . '?' . $post['published_at']);
                if (isset($post['cover_photo']) && !empty($post['cover_photo'])) {
                    $imageUrl = url(UPLOADS . '/blog/' . $post['cover_photo'] . '?' . $post['published_at']);
                }
                $this->urls[] = [
                    'url' => url([$blogBaseSlug, 'post', $post['slug']]),
                    'image' => $imageUrl,
                    'links' => $this->getLinksSubNodes('blog', $post['slug']),
                    'lastmod' => date('c', $post['published_at']),
                    'changefreq' => 'never'
                ];
            }
        }
        // Tags URLs
        foreach ($tags as $tag) {
            $this->urls[] = [
                'url' => url([$blogBaseSlug, 'tag', $tag['slug']]),
                'lastmod' => null,
                'changefreq' => 'daily'
            ];
        }
    }
}
