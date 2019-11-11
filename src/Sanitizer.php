<?php

namespace ZendLib\NetscapeBookmark;

class Sanitizer implements SanitizerInterface
{
    public function sanitize($str)
    {
        // trim comments
        $sanitized = preg_replace('@<!--.*?-->@mis', '', $str);

        // keep one XML element per line to prepare for linear parsing
        $sanitized = preg_replace('@>(\s*?)<@mis', ">\n<", $sanitized);

        // trim unused metadata
        $sanitized = preg_replace('@(<!DOCTYPE|<META|<TITLE|<H1|<P).*\n@i', '', $sanitized);

        // trim whitespace
        $sanitized = trim($sanitized);

        // trim carriage returns, replace tabs by a single space
        $sanitized = str_replace(array("\r", "\t"), array('',' '), $sanitized);

        // convert multiline descriptions to one-line descriptions
        // line feeds are converted to <br>
        $sanitized = preg_replace_callback(
            '@<DD>(.*?)(</?(:?DT|DD|DL))@mis',
            function ($match) {
                return '<DD>'.str_replace("\n", '<br>', trim($match[1])).PHP_EOL. $match[2];
            },
            $sanitized
        );

        // convert multiline descriptions inside <A> tags to one-line descriptions
        // line feeds are converted to <br>
        $sanitized = preg_replace_callback(
            '@<A(.*?)</A>@mis',
            function ($match) {
                return '<A'.str_replace("\n", '<br>', trim($match[1])).'</A>';
            },
            $sanitized
        );

        // concatenate all information related to the same entry on the same line
        // e.g. <A HREF="...">My Link</A><DD>List<br>- item1<br>- item2
        $sanitized = preg_replace('@\n<br>@mis', "<br>", $sanitized);
        $sanitized = preg_replace('@\n<DD@i', '<DD', $sanitized);

        return $sanitized;
    }

    public function sanitizeTagString($tagString)
    {
        $tags = explode(' ', strtolower($tagString));

        foreach ($tags as $key => &$value) {
            if (ctype_alnum($value)) {
                continue;
            }

            // trim leading punctuation
            $value = preg_replace('/^[[:punct:]]/', '', $value);

            // trim all but alphanumeric characters, underscores and non-leading dashes
            $value = preg_replace('/[^\p{L}\p{N}\-_]++/u', '', $value);

            if ($value == '') {
                unset($tags[$key]);
            }
        }

        return implode(' ', $tags);
    }
}
