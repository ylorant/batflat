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

namespace Inc\Modules\Blog;

use DateTime;
use Exception;
use Inc\Core\SiteModule;

class Site extends SiteModule
{
    public const DEFAULT_SLUG = 'blog';

    /** @var string $baseSlug */
    protected string $baseSlug = self::DEFAULT_SLUG;

    /** @var string */
    protected string $moduleDirectory = MODULES . '/blog';

    /** @var string */
    protected string $defaultCover = MODULES . '/blog/img/default.jpg';

    /**
     * @throws Exception
     */
    public function init()
    {
        $slug = parseURL();
        if (count($slug) == 3 && $slug[0] == 'blog' && $slug[1] == 'post') {
            $row = $this->db('blog')->where('status', '>=', 1)->where('published_at', '<=', time())->where('slug', $slug[2])->oneArray();
            if ($row) {
                $this->core->loadLanguage($row['lang']);
            }
        }

        $this->core->addCSS(url($this->moduleDirectory . '/assets/css/blog.css'));

        $this->baseSlug = $this->settings('blog.slug') ? ltrim($this->settings('blog.slug'), '/') : $this->baseSlug;
        $this->defaultCover = $this->settings('blog.default_cover') ? UPLOADS . '/blog/' . ltrim($this->settings('blog.default_cover'), '/') : $this->defaultCover;

        $this->tpl->set('blogBaseSlug', $this->baseSlug);
        $this->tpl->set('latestPosts', $this->getLatestPosts());
        $this->tpl->set('allTags', function () {
            return $this->getAllTags();
        });
    }

    /**
     *
     */
    public function routes()
    {
        $this->route($this->baseSlug, 'importAllPosts');
        $this->route($this->baseSlug . '/(:int)', 'importAllPosts');
        $this->route($this->baseSlug . '/post/(:str)', 'importPost');
        $this->route($this->baseSlug . '/tag/(:str)', 'importTagPosts');
        $this->route($this->baseSlug . '/tag/(:str)/(:int)', 'importTagPosts');
        $this->route($this->baseSlug . '/feed/(:str)', 'generateRSS');
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLatestPosts(): array
    {
        $limit = $this->settings('blog.latestPostsCount');
        return $this->db('blog')
            ->leftJoin('users', 'users.id = blog.user_id')
            ->where('blog.status', 2)
            ->where('published_at', '<=', time())
            ->where('lang', $_SESSION['lang'])
            ->desc('published_at')
            ->limit($limit)
            ->select(['blog.id', 'blog.title', 'blog.slug', 'users.username', 'users.fullname'])
            ->toArray();
    }

    /**
     * @return array
     */
    public function getAllTags(): array
    {
        return $this->db('blog_tags')
            ->leftJoin('blog_tags_relationship', 'blog_tags.id = blog_tags_relationship.tag_id')
            ->leftJoin('blog', 'blog.id = blog_tags_relationship.blog_id')
            ->where('blog.status', 2)
            ->where('blog.lang', $_SESSION['lang'])
            ->where('blog.published_at', '<=', time())
            ->select(['blog_tags.name', 'blog_tags.slug', 'count' => 'COUNT(blog_tags.name)'])
            ->group('blog_tags.name')
            ->toArray();
    }

    /**
     * Get single post data
     *
     * @param null $slug
     * @return void
     * @throws Exception
     */
    public function importPost($slug = null)
    {
        $assign = [];
        if (!empty($slug)) {
            if ($this->core->loginCheck()) {
                $row = $this->db('blog')->where('slug', $slug)->oneArray();
            } else {
                $row = $this->db('blog')
                ->where('status', '>=', 1)
                ->where('published_at', '<=', time())
                ->where('slug', $slug)->oneArray();
            }

            if (!empty($row)) {
                // get dependences
                $row['author'] = $this->db('users')->where('id', $row['user_id'])->oneArray();
                
                // If no user found for given id, then we fetch the first user
                if (!$row['author']) {
                    $row['author'] = $this->db('users')->asc('id')->limit(1)->oneArray();
                }
                
                $row['author']['name'] = !empty($row['author']['fullname']) ? $row['author']['fullname'] : $row['author']['username'];
                $row['author']['avatar'] = url(UPLOADS . '/users/' . $row['author']['avatar']);

                // cover
                if (!empty($row['cover_photo'])) {
                    $row['cover_url'] = url(UPLOADS . '/blog/' . $row['cover_photo']) . '?' . $row['published_at'];
                } else {
                    $row['cover_url'] = url($this->defaultCover);
                }

                $row['url'] = url('blog/post/' . $row['slug']);
                $row['disqus_identifier'] = md5($row['id'] . $row['url']);

                // tags
                $row['tags'] = $this->db('blog_tags')
                    ->leftJoin('blog_tags_relationship', 'blog_tags.id = blog_tags_relationship.tag_id')
                    ->where('blog_tags_relationship.blog_id', $row['id'])
                    ->toArray();
                if ($row['tags']) {
                    array_walk($row['tags'], function (&$tag) {

                        $tag['url'] = url($this->baseSlug . '/tag/' . $tag['slug']);
                    });
                }

                $assign = $row;
                // Markdown
                if (intval($assign['markdown'])) {
                    $parsedown = new \Inc\Core\Lib\Parsedown();
                    $assign['content'] = $parsedown->text($assign['content']);
                    $assign['intro'] = $parsedown->text($assign['intro']);
                }

                // Admin access only
                if ($this->core->loginCheck()) {
                    if ($assign['published_at'] > time()) {
                        $assign['content'] = '<div class="alert alert-warning">' . $this->lang('post_time') . '</div>' . $assign['content'];
                    }
                    if ($assign['status'] == 0) {
                        $assign['content'] = '<div class="alert alert-warning">' . $this->lang('post_draft') . '</div>' . $assign['content'];
                    }
                }

                // date formatting
                $assign['published_at'] = (new DateTime(date("YmdHis", $assign['published_at'])))->format($this->settings('blog.dateformat'));
                $keys = array_keys($this->core->lang['blog']);
                $vals = array_values($this->core->lang['blog']);
                $assign['published_at'] = str_replace($keys, $vals, strtolower($assign['published_at']));
                $this->setTemplate("post.html");
                $this->tpl->set('page', ['title' => $assign['title'], 'desc' => trim(mb_strimwidth(htmlspecialchars(strip_tags(preg_replace('/\{(.*?)\}/', null, $assign['content']))), 0, 155, "...", "utf-8"))]);
                $this->tpl->set('post', $assign);
                $this->tpl->set('blog', [
                'title' => $this->settings('blog.title'),
                'desc' => $this->settings('blog.desc')
                ]);
            } else {
                return $this->core->module->pages->get404();
            }
        }

        $this->core->append('<link rel="alternate" type="application/rss+xml" title="RSS" href="' . url(['blog', 'feed', $row['lang']]) . '">', 'header');
        $this->core->append('<meta property="og:url" content="' . url(['blog', 'post', $row['slug']]) . '">', 'header');
        $this->core->append('<meta property="og:type" content="article">', 'header');
        $this->core->append('<meta property="og:title" content="' . $row['title'] . '">', 'header');
        $this->core->append('<meta property="og:description" content="' . trim(mb_strimwidth(htmlspecialchars(strip_tags(preg_replace('/\{(.*?)\}/', null, $assign['content']))), 0, 155, "...", "utf-8")) . '">', 'header');
        if (!empty($row['cover_photo'])) {
            $this->core->append('<meta property="og:image" content="' . url(UPLOADS . '/blog/' . $row['cover_photo']) . '?' . $row['published_at'] . '">', 'header');
        }
        $this->core->append($this->draw('disqus.html', ['isPost' => true]), 'footer');
    }

    /**
     * Get array with all posts
     *
     * @param int $page
     * @throws Exception
     */
    public function importAllPosts(int $page = 1)
    {
        $page = max($page, 1);
        $perpage = $this->settings('blog.perpage');
        $rows = $this->db('blog')
                            ->where('status', 2)
                            ->where('published_at', '<=', time())
                            ->where('lang', $_SESSION['lang'])
                            ->limit($perpage)->offset(($page - 1) * $perpage)
                            ->desc('published_at')
                            ->toArray();
        $assign = [
            'title' => $this->settings('blog.title'),
            'desc' => $this->settings('blog.desc'),
            'posts' => []
        ];
        foreach ($rows as $row) {
        // get dependences
            $row['author'] = $this->db('users')->where('id', $row['user_id'])->oneArray();
            // If no user found for given id, then we fetch the first user
            if (!$row['author']) {
                $row['author'] = $this->db('users')->asc('id')->limit(1)->oneArray();
            }
            
            $row['author']['name'] = !empty($row['author']['fullname']) ? $row['author']['fullname'] : $row['author']['username'];
        // cover
            if (!empty($row['cover_photo'])) {
                $row['cover_url'] = url(UPLOADS . '/blog/' . $row['cover_photo']) . '?' . $row['published_at'];
            } else {
                $row['cover_url'] = url($this->defaultCover);
            }

            // tags
            $row['tags'] = $this->db('blog_tags')
                                ->leftJoin('blog_tags_relationship', 'blog_tags.id = blog_tags_relationship.tag_id')
                                ->where('blog_tags_relationship.blog_id', $row['id'])
                                ->toArray();
            if ($row['tags']) {
                array_walk($row['tags'], function (&$tag) {

                    $tag['url'] = url($this->baseSlug . '/tag/' . $tag['slug']);
                });
            }

            // date formatting
            $row['published_at'] = (new DateTime(date("YmdHis", $row['published_at'])))->format($this->settings('blog.dateformat'));
            $keys = array_keys($this->core->lang['blog']);
            $vals = array_values($this->core->lang['blog']);
            $row['published_at'] = str_replace($keys, $vals, strtolower($row['published_at']));

            // generating URLs
            $row['url'] = url($this->baseSlug . '/post/' . $row['slug']);
            $row['disqus_identifier'] = md5($row['id'] . $row['url']);

            if (!empty($row['intro'])) {
                $row['content'] = $row['intro'];
            }

            if (intval($row['markdown'])) {
                if (!isset($parsedown)) {
                    $parsedown = new \Inc\Core\Lib\Parsedown();
                }
                $row['content'] = $parsedown->text($row['content']);
            }

            $assign['posts'][$row['id']] = $row;
        }

        $count = $this->db('blog')->where('status', 2)->where('published_at', '<=', time())->where('lang', $_SESSION['lang'])->count();
        if ($page > 1) {
            $prev['url'] = url($this->baseSlug . '/' . ($page - 1));
            $this->tpl->set('prev', $prev);
        }
        if ($page < $count / $perpage) {
            $next['url'] = url($this->baseSlug . '/' . ($page + 1));
            $this->tpl->set('next', $next);
        }

        $this->setTemplate("blog.html");
        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc']]);
        $this->tpl->set('blog', $assign);
        $this->core->append('<link rel="alternate" type="application/rss+xml" title="RSS" href="' . url([$this->baseSlug, 'feed', $_SESSION['lang']]) . '">', 'header');
        $this->core->append($this->draw('disqus.html', ['isBlog' => true]), 'footer');
    }

    /**
     * Get array with all posts
     *
     * @param string $slug
     * @param int $page
     * @return void
     * @throws Exception
     */
    public function importTagPosts(string $slug, int $page = 1)
    {
        $page = max($page, 1);
        $perpage = $this->settings('blog.perpage');
        if (!($tag = $this->db('blog_tags')->oneArray('slug', $slug))) {
            return $this->core->module->pages->get404();
        }

        $rows = $this->db('blog')
                        ->leftJoin('blog_tags_relationship', 'blog_tags_relationship.blog_id = blog.id')
                        ->where('blog_tags_relationship.tag_id', $tag['id'])
                        ->where('lang', $_SESSION['lang'])
                        ->where('status', 2)->where('published_at', '<=', time())
                        ->limit($perpage)
                        ->offset(($page - 1) * $perpage)
                        ->desc('published_at')
                        ->toArray();
        $assign = [
            'title' => '#' . $tag['name'],
            'desc' => $this->settings('blog.desc'),
            'posts' => []
        ];
        foreach ($rows as $row) {
        // get dependences
            $row['author'] = $this->db('users')->where('id', $row['user_id'])->oneArray();
            $row['author']['name'] = !empty($row['author']['fullname']) ? $row['author']['fullname'] : $row['author']['username'];
        // cover
            if (!empty($row['cover_photo'])) {
                $row['cover_url'] = url(UPLOADS . '/blog/' . $row['cover_photo']) . '?' . $row['published_at'];
            } else {
                $row['cover_url'] = url($this->defaultCover);
            }

            // tags
            $row['tags'] = $this->db('blog_tags')
                                ->leftJoin('blog_tags_relationship', 'blog_tags.id = blog_tags_relationship.tag_id')
                                ->where('blog_tags_relationship.blog_id', $row['id'])
                                ->toArray();
            if ($row['tags']) {
                array_walk($row['tags'], function (&$tag) {

                    $tag['url'] = url($this->baseSlug . '/tag/' . $tag['slug']);
                });
            }

            // date formatting
            $row['published_at'] = (new DateTime(date("YmdHis", $row['published_at'])))->format($this->settings('blog.dateformat'));
            $keys = array_keys($this->core->lang['blog']);
            $vals = array_values($this->core->lang['blog']);
            $row['published_at'] = str_replace($keys, $vals, strtolower($row['published_at']));

            // generating URLs
            $row['url'] = url('blog/post/' . $row['slug']);
            $row['disqus_identifier'] = md5($row['id'] . $row['url']);

            if (!empty($row['intro'])) {
                $row['content'] = $row['intro'];
            }

            if (intval($row['markdown'])) {
                if (!isset($parsedown)) {
                    $parsedown = new \Inc\Core\Lib\Parsedown();
                }
                $row['content'] = $parsedown->text($row['content']);
            }

            $assign['posts'][$row['id']] = $row;
        }

        $count = $this->db('blog')->leftJoin('blog_tags_relationship', 'blog_tags_relationship.blog_id = blog.id')->where('status', 2)->where('lang', $_SESSION['lang'])->where('published_at', '<=', time())->where('blog_tags_relationship.tag_id', $tag['id'])->count();
        if ($page > 1) {
            $prev['url'] = url($this->baseSlug . '/tag/' . $slug . '/' . ($page - 1));
            $this->tpl->set('prev', $prev);
        }
        if ($page < $count / $perpage) {
            $next['url'] = url($this->baseSlug . '/tag/' . $slug . '/' . ($page + 1));
            $this->tpl->set('next', $next);
        }

        $this->setTemplate("blog.html");
        $this->tpl->set('page', ['title' => $assign['title'] , 'desc' => $assign['desc']]);
        $this->tpl->set('blog', $assign);
        $this->core->append($this->draw('disqus.html', ['isBlog' => true]), 'footer');
    }

    /**
     * @param string $lang
     * @throws Exception
     */
    public function generateRSS(string $lang)
    {
        header('Content-type: application/xml');
        $this->setTemplate(false);
        $rows = $this->db('blog')
                ->where('status', 2)
                ->where('published_at', '<=', time())
                ->where('lang', $lang)
                ->limit(5)
                ->desc('published_at')
                ->toArray();
        if (!empty($rows)) {
            foreach ($rows as &$row) {
                if (!empty($row['intro'])) {
                    $row['content'] = $row['intro'];
                }

                $row['content'] = preg_replace('/{(.*?)}/', '', strip_tags($row['content']));
                $row['url'] = url('blog/post/' . $row['slug']);
                if (!empty($row['cover_photo'])) {
                    $row['cover_url'] = url(UPLOADS . '/blog/' . $row['cover_photo']) . '?' . $row['published_at'];
                } else {
                    $row['cover_url'] = url($this->defaultCover);
                }
                $row['published_at'] = (new DateTime(date("YmdHis", $row['published_at'])))->format('D, d M Y H:i:s O');
            }

            echo $this->draw('feed.xml', ['posts' => $rows]);
        }
    }
}
