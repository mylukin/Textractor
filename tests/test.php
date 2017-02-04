<?php
/**
 * Created by PhpStorm.
 * User: lukin
 * Date: 04/02/2017
 * Time: 13:27
 */

date_default_timezone_set('Asia/Shanghai');

require __DIR__ . '/../vendor/autoload.php';

// 新闻地址
$url = isset($argv[1]) ? $argv[1] : 'http://news.163.com/17/0204/08/CCDTBQ9E000189FH.html';
// 读取配置
$config = include __DIR__ . '/../src/config.php';
// 实例化类
$textractor = new \Lukin\Textractor\Textractor($config);
// 下载并解析文章
$article = $textractor->download($url)->parse();

printf('<div id="url">URL: %s</div>' . PHP_EOL, $url);
printf('<div id="title">Title: %s</div>' . PHP_EOL, $article->getTitle());
printf('<div id="published">Publish: %s</div>' . PHP_EOL, $article->getPublishDate());
printf('<div id="text">Text: <pre>%s</pre></div>' . PHP_EOL, $article->getText());
printf('<div id="html">Content: %s</div>' . PHP_EOL, $article->getHTML());
