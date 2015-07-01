<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\Tests\DoctrineDto\Generator;

use KonstantinKuklin\DoctrineDto\Configuration\MapGeneratorInterface;

class EntityDtoSimpleGenerator implements MapGeneratorInterface
{

    /**
     * {@inheritdoc}
     */
    public function getPath($classPath)
    {
        $dtoClassPath = str_replace('Entity', 'Dto', $classPath);
        return $dtoClassPath;
    }
}