<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\Tests\DoctrineDto\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="car")
 */
class CarEntity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $color;

    /**
     * @ORM\ManyToOne(targetEntity="UserEntity")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     **/
    private $owner;
}