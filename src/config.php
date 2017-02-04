<?php
/**
 * Created by PhpStorm.
 * User: lukin
 * Date: 04/02/2017
 * Time: 13:12
 */

return [
    // 按行分析的深度，默认为6 0-6行满足 limit_count
    'depth' => 6,
    // 字符限定数，当分析的文本数量达到限定数则认为进入正文内容
    'limit_count' => 180,
    // 确定文章正文头部时，向上查找，连续的空行到达 head_empty_lines，则停止查找
    'head_empty_lines' => 2,
    // 用于确定文章结束的字符数
    'end_limit_char_count' => 20,
    // 文章追加模式
    'append_mode' => false,
];