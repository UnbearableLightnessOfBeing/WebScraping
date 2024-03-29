<?php

declare(strict_types = 1);

namespace App\Models;

require_once __DIR__ . '/../parser/news-parser.class.php';

use App\Model;
use App\Exceptions\RouteNotFoundException;
use App\Exceptions\NoPostsException;
use Exception;

class NewsPosts extends Model {

    public function getPosts(): array {

        $this->overrideCors();

        $stmt = $this->db->prepare('select id, title, overview, substring(text, 1, 200) as text, rating from posts;');

        $stmt->execute();

        if($stmt->rowCount() == 0) {
            throw new NoPostsException('No posts');
        }

        $rawData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // var_dump($rawData);

        $table = array();

        foreach($rawData as $post) {
            array_push($table, array(
                'title' => $post['title'],
                'overview' => $post['overview'],
                'text' => $post['text'] . '...',
                'rating' => (int)$post['rating'],
                'link' => 'http://localhost/posts/' . $post['id']
            ));
        }
        
        return $table;
    }

    public function getSinglePost(int $id) {

        $this->overrideCors();
        
        $stmt = $this->db->prepare('SELECT title, overview, text, picture, rating FROM posts WHERE id = ? LIMIT 0,1');

        $stmt->execute([$id]);

        if($stmt->rowCount() == 0) {
            throw new RouteNotFoundException;
        }

        $post = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $post;
    }

    public function updateRating(int $id, int $rating) {

        $stmt = $this->db->prepare('update posts set rating = ? where id = ?;');

        $stmt->execute([$rating, $id]);

        if($stmt->rowCount() == 0) {
            throw new RouteNotFoundException;
        }
    }
    
    public function refreshPosts() {
        $rbcNewsParser = new  \App\Parser\NewsParser('https://www.rbc.ru/', 15);
        $rbcNewsParser->setLinkPath('.js-news-feed-list > .news-feed__item');
        
        // rbc.ru site publishes different kinds of news posts with different DOM structure, so the parser looks for an appropriate set of paths to each post
        // сайт rbc.ru публикует различные виды новостных постов с разной структурой DOM, поэтому парсер ищет подходящие параметры для каждого поста
        
        $rbcNewsParser->setTitlePath('.article__content .article__header .article__header__title h1',
                                     '.article__main .article__header .article__header-right .article__title',
                                     '.interview__container .interview__header h1',
                                     '.section--main .section__container .section__title > span');
        $rbcNewsParser->setOverviewPath('.article__content .article__text__overview span',
                                        '.article__main .article__header .article__header-right p',
                                        '.interview__container .interview__header .interview__desc',
                                        '.article .section__container .article__main-row .article__main-txt');
        $rbcNewsParser->setTextPath('.article__content .article__text  p',
                                    '.article__main .article__content > *',
                                    '.interview__container > div',
                                    '.article .section .container > * > *');
        $rbcNewsParser->setPicturePath('.article__content .article__text .article__main-image img',
                                       '.article__main .article__header .lazy-blur__imgS',
                                       '',
                                       '.article .section__container .article__main-row .article__main-img > img');

        $rbcNewsParser->parseNews();
        // $rbcNewsParser->showPostLinks();
        // $rbcNewsParser->showPosts();
        


       try {
            $posts = $rbcNewsParser->posts();

            $stmnt = $this->db->prepare('DELETE FROM posts');
            $stmnt->execute();

            $stmnt = $this->db->prepare('ALTER TABLE posts AUTO_INCREMENT = 1');
            $stmnt->execute();

            //putting data into the database
            foreach($posts as $post){
                $stmnt = $this->db->prepare('insert into posts (title, overview, text, picture, rating, link) values (?, ?, ?, ?, ?, ?)');
                $stmnt->execute([$post['title'], $post['overview'], $post['text'], $post['picture'], $post['rating'], $post['link']]);
            }
       } catch (\PDOException) {
           throw new Exception('Error during database managing');
       }
    }


    private function overrideCors() {
        // Allow from any origin
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Origin, Authorization, X-Requested-With, Content-Type, Accept");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }
    
        // Access-Control headers are received during OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                // may also be using PUT, PATCH, HEAD etc
                header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
    
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: Origin, Authorization, X-Requested-With, Content-Type, Accept");
    
            exit(0);
        }
    }
}