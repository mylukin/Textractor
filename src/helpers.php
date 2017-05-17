<?php
/**
 * Created by PhpStorm.
 * User: lukin
 * Date: 27/02/2017
 * Time: 21:27
 */
if (!function_exists('clear_space')) {
    /**
     * 清除空白
     *
     * @param string $content
     * @return string
     */
    function clear_space($content)
    {
        if (strlen($content) == 0) return $content;
        $r = $content;
        $r = str_replace(array(chr(9), chr(10), chr(13)), '', $r);
        while (strpos($r, chr(32) . chr(32)) !== false || strpos($r, '&nbsp;') !== false) {
            $r = str_replace(array(
                '&nbsp;',
                chr(32) . chr(32),
            ),
                chr(32),
                $r
            );
        }
        return $r;
    }
}

if (!function_exists('mid')) {
    /**
     * 内容截取，支持正则
     *
     * $start,$end,$clear 支持正则表达式，“@”斜杠开头为正则模式
     * $clear 支持数组
     *
     * @param string $content 内容
     * @param string $start 开始代码
     * @param string $end 结束代码
     * @param string|array $clear 清除内容
     * @return string
     */
    function mid($content, $start, $end = null, $clear = null)
    {
        if (empty($content) || empty($start)) return null;
        if (strncmp($start, '@', 1) === 0) {
            if (preg_match($start, $content, $args)) {
                $start = $args[0];
            }
        }

        $start_len = strlen($start);
        $result = null;
        // 找到开始的位置
        $start_pos = stripos($content, $start);
        if ($start_pos === false) return null;
        // 获取剩余内容
        $remain_content = substr($content, -(strlen($content) - $start_pos - $start_len));
        if ($end === null) {
            $length = null;
        } else {
            // 正则查找结束符
            if ($end && strncmp($end, '@', 1) === 0) {
                if (preg_match($end, $remain_content, $args, PREG_OFFSET_CAPTURE)) {
                    if ($args[0][1] == strlen($remain_content)) {
                        $end = null;
                    } else {
                        $end = $args[0][0];
                    }
                }
            }
            if ($end == null) {
                $length = null;
            } else {
                $length = stripos($remain_content, $end);
            }
        }

        if ($start_pos !== false) {
            if ($length === null) {
                $result = trim(substr($content, $start_pos + $start_len));
            } else {
                $result = trim(substr($content, $start_pos + $start_len, $length));
            }
        }

        if ($result && $clear) {
            if (is_array($clear)) {
                foreach ($clear as $v) {
                    if (strncmp($v, '@', 1) === 0) {
                        $result = preg_replace($v, '', $result);
                    } else {
                        if (strpos($result, $v) !== false) {
                            $result = str_replace($v, '', $result);
                        }
                    }
                }
            } else {
                if (strncmp($clear, '@', 1) === 0) {
                    $result = preg_replace($clear, '', $result);
                } else {
                    if (strpos($result, $clear) !== false) {
                        $result = str_replace($clear, '', $result);
                    }
                }
            }
        }
        return $result;
    }
}

if (!function_exists('close_tags')) {
    /**
     * 关闭html标签
     *
     * @param string $html
     * @return mixed|string
     */
    function close_tags($phtml)
    {
        $param = array(
         'html' => $phtml, //必填
         'options' => array(
         'tagArray' => array(),
         'type' => 'NEST',
         'length' => null,
         'lowerTag' => TRUE,
         'XHtmlFix' => TRUE
         ));
        //参数的默认值
        $html = '';
        $tagArray = array();
        $type = 'NEST';
        $length = null;
        $lowerTag = TRUE;
        $XHtmlFix = TRUE;
        //首先获取一维数组，即 $html 和 $options （如果提供了参数）
        extract($param);
        //如果存在 options，提取相关变量
        if (isset($options)) {
            extract($options);
        }
        $result = ''; //最终要返回的 html 代码
        $tagStack = array(); //标签栈，用 array_push() 和 array_pop() 模拟实现
        $contents = array(); //用来存放 html 标签
        $len = 0; //字符串的初始长度
        //设置闭合标记 $isClosed，默认为 TRUE, 如果需要就近闭合，成功匹配开始标签后其值为 false,成功闭合后为 true
                $isClosed = true;
        //将要处理的标签全部转为小写
                $tagArray = array_map('strtolower', $tagArray);
        //“合法”的单闭合标签
        $singleTagArray = array(
            '<meta',
            '<link',
            '<base',
            '<br',
            '<hr',
            '<input',
            '<img'
        );
        //校验匹配模式 $type，默认为 NEST 模式
        $type = strtoupper($type);
        if (!in_array($type, array('NEST', 'CLOSE'))) {
            $type = 'NEST';
        }
        //以一对 < 和 > 为分隔符，将原 html 标签和标签内的字符串放到数组中
        $contents = preg_split("/(<[^>]+?>)/si", $html, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($contents as $tag) {
            if ('' == trim($tag)) {
                $result .= $tag;
                continue;
            }
            //匹配标准的单闭合标签，如<br />
            if (preg_match("/<(\w+)[^\/>]*?\/>/si", $tag)) {
                $result .= $tag;
                continue;
            }
            //匹配开始标签，如果是单标签则出栈
            else if (preg_match("/<(\w+)[^\/>]*?>/si", $tag, $match)) {
            //如果上一个标签没有闭合，并且上一个标签属于就近闭合类型
            //则闭合之，上一个标签出栈
            //如果标签未闭合
                if (false === $isClosed) {
                    //就近闭合模式，直接就近闭合所有的标签
                    if ('CLOSE' == $type) {
                        $result .= '</' . end($tagStack) . '>';
                        array_pop($tagStack);
                    }
                    //默认的嵌套模式，就近闭合参数提供的标签
                    else {
                        if (in_array(end($tagStack), $tagArray)) {
                            $result .= '</' . end($tagStack) . '>';
                            array_pop($tagStack);
                        }
                    }
                }
                //如果参数 $lowerTag 为 TRUE 则将标签名转为小写
                $matchLower = $lowerTag == TRUE ? strtolower($match[1]) : $match[1];
                $tag = str_replace('<' . $match[1], '<' . $matchLower, $tag);
                //开始新的标签组合
                $result .= $tag;
                array_push($tagStack, $matchLower);
                //如果属于约定的的单标签，则闭合之并出栈
                foreach ($singleTagArray as $singleTag) {
                    if (stripos($tag, $singleTag) !== false) {
                        if ($XHtmlFix == TRUE) {
                            $tag = str_replace('>', ' />', $tag);
                        }
                        array_pop($tagStack);
                    }
                }
                //就近闭合模式，状态变为未闭合
                if ('CLOSE' == $type) {
                    $isClosed = false;
                }
                //默认的嵌套模式，如果标签位于提供的 $tagArray 里，状态改为未闭合
                else {
                    if (in_array($matchLower, $tagArray)) {
                        $isClosed = false;
                    }
                }
                unset($matchLower);
            }
            //匹配闭合标签，如果合适则出栈
            else if (preg_match("/<\/(\w+)[^\/>]*?>/si", $tag, $match)) {
                //如果参数 $lowerTag 为 TRUE 则将标签名转为小写
                $matchLower = $lowerTag == TRUE ? strtolower($match[1]) : $match[1];
                if (end($tagStack) == $matchLower) {
                    $isClosed = true; //匹配完成，标签闭合
                    $tag = str_replace('</' . $match[1], '</' . $matchLower, $tag);
                    $result .= $tag;
                    array_pop($tagStack);
                }
                unset($matchLower);
            }
            //匹配注释，直接连接 $result
            else if (preg_match("/<!--.*?-->/si", $tag)) {
                $result .= $tag;
            }
            //将字符串放入 $result ，顺便做下截断操作
            else {
                if (is_null($length) || $len + mb_strlen($tag) < $length) {
                    $result .= $tag;
                    $len += mb_strlen($tag);
                } else {
                    $str = mb_substr($tag, 0, $length - $len + 1);
                    $result .= $str;
                    break;
                }
            }
        }
        //如果还有将栈内的未闭合的标签连接到 $result
        while (!empty($tagStack)) {
            $result .= '</' . array_pop($tagStack) . '>';
        }
        return $result;
    }
}