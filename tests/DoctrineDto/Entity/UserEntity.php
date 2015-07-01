<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\Tests\DoctrineDto\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class UserEntity
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
    private $name;

    /**
     * @ORM\OneToOne(targetEntity="UserEntity")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     **/
    private $parentUser;

    /**
     * @ORM\OneToOne(targetEntity="JobEntity", mappedBy="customer")
     **/
    private $job;

    /**
     * @ORM\ManyToMany(targetEntity="PhoneEntity")
     * @ORM\JoinTable(name="user_phone",
     *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="phone_id", referencedColumnName="id")}
     * )
     **/
    private $phoneNumberList;
}