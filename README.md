# Textractor

An efficient class library for extracting text from HTML.

一个高效的从HTML中提取正文的类库。

正文提取采用了基于文本密度的提取算法，支持从压缩的HTML文档中提取正文，每个页面平均提取时间为30ms，正确率在95%以上。

## 特色

- 标签无关，提取正文不依赖标签；
- 支持从压缩的HTML文档中提取正文内容；
- 支持带标签输出原始正文；
- 核心算法简洁高效，平均提取时间在30ms左右。

## 安装

1. 安装包文件
  ```shell
  composer require "mylukin/textractor:dev-master"
  ```

2. 添加 `ServiceProvider` 到您项目 `config/app.php` 中的 `providers` 部分:

  ```php
  Lukin\Textractor\TextractorServiceProvider::class,
  ```

3. 创建配置文件:

  ```shell
  php artisan vendor:publish --provider="Lukin\Textractor\TextractorServiceProvider"
  ```

  然后请修改 `config/textractor.php` 中对应的项即可。
  
## 使用

```php
<?php
$url = 'http://news.163.com/17/0204/08/CCDTBQ9E000189FH.html';
// 创建提取实例
$textractor = new \Lukin\Textractor\Textractor();
// 下载并解析文章
$article = $textractor->download($url)->parse();

printf('<div id="url">URL: %s</div>' . PHP_EOL, $url);
printf('<div id="title">Title: %s</div>' . PHP_EOL, $article->getTitle());
printf('<div id="published">Publish: %s</div>' . PHP_EOL, $article->getPublishDate());
printf('<div id="text">Text: <pre>%s</pre></div>' . PHP_EOL, $article->getText());
printf('<div id="html">Content: %s</div>' . PHP_EOL, $article->getHTML());

```

## License

MIT