DoctrineDto
======

[![Build Status](https://secure.travis-ci.org/KonstantinKuklin/DoctrineDto.png?branch=master)](https://travis-ci.org/KonstantinKuklin/DoctrineDto)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/KonstantinKuklin/DoctrineDto/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/KonstantinKuklin/DoctrineDto/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/KonstantinKuklin/DoctrineDto/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/KonstantinKuklin/DoctrineDto/?branch=master)
[![GitHub release](https://img.shields.io/github/release/KonstantinKuklin/DoctrineDto.svg)](https://github.com/KonstantinKuklin/DoctrineDto/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/konstantin-kuklin/doctrine-dto.svg)](https://packagist.org/packages/konstantin-kuklin/doctrine-dto)
[![Daily Downloads](https://img.shields.io/packagist/dd/konstantin-kuklin/doctrine-dto.svg)](https://packagist.org/packages/konstantin-kuklin/doctrine-dto)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.3-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/konstantin-kuklin/doctrine-dto.svg)](https://packagist.org/packages/konstantin-kuklin/doctrine-dto)

What is DoctrineDto?
-----------------
Library for getting Data Transfer Object from a database through Doctrine.
If you are using a service approach to development, this library can be useful for you.

Requirements
------------
Single dependency: Doctrine orm library
Also you need to have PHP >= 5.3

Installation
------------
The simplest way to add DoctrineDto is execute command:
```
composer require "konstantin-kuklin/doctrine-dto" "dev-master"
```

Usage example
-------------
Initialize Dto -> Entity class map:
```php
// static map rules here:
$map = new Map(
    array(
        'Path\To\UserEntity' => 'Path\To\UserDto',
        'Path\To\AnotherEntity' => 'Path\To\AnotherDto'
    )
);
// class to dynamic class map generation
$map->addMapGeneratorElement(new EntityDtoSimpleGenerator());

// set class map
DtoClassMap::setMap($map, $map->getFlippedMap());
```

Add custom hydrator in your code with such example:
```php
$em->getConfiguration()->addCustomHydrationMode('DtoHydrator', 'KonstantinKuklin\DoctrineDto\Hydrator\DtoHydrator');
$query = $em->createQuery('SELECT u FROM CmsUser u');
$results = $query->getResult('DtoHydrator');
```

Usage with Symfony
-------------
For using with Symfony framework go to [DoctrineDtoBundle](https://github.com/KonstantinKuklin/DoctrineDtoBundle).