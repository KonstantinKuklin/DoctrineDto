<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\Tests\DoctrineDto\Dto;


class UserDto
{
    public $_isInitialized = false;

    public $id;
    public $name;
    public $parentUser;
    public $phoneNumberList;
    public $job;
}