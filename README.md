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

先看看源码吧，不复杂！

## License

MIT