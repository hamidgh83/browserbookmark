<?php
namespace ZendLib\NetscapeBookmar;

interface SanitizerInterface
{
    public function sanitizer ($str);

    public function sanitizeTagString ($tagString);
}