<?php
/**
 * @author KonstantinKuklin <konstantin.kuklin@gmail.com>
 */

namespace KonstantinKuklin\Tests\DoctrineDto;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\Setup;
use KonstantinKuklin\DoctrineDto\Configuration\Map;
use KonstantinKuklin\DoctrineDto\DtoClassMap;
use KonstantinKuklin\Tests\DoctrineDto\Dto\CarDto;
use KonstantinKuklin\Tests\DoctrineDto\Dto\JobDto;
use KonstantinKuklin\Tests\DoctrineDto\Dto\PhoneDto;
use KonstantinKuklin\Tests\DoctrineDto\Dto\UserDto;
use KonstantinKuklin\Tests\DoctrineDto\Entity\CarEntity;
use KonstantinKuklin\Tests\DoctrineDto\Entity\JobEntity;
use KonstantinKuklin\Tests\DoctrineDto\Entity\PhoneEntity;
use KonstantinKuklin\Tests\DoctrineDto\Entity\UserEntity;
use KonstantinKuklin\Tests\DoctrineDto\Generator\EntityDtoSimpleGenerator;
use PHPUnit_Framework_TestCase;


class DtoHydratorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    public static function setUpBeforeClass()
    {
        $map = new Map(
            array(
                get_class(new UserEntity()) => get_class(new UserDto()),
                get_class(new PhoneEntity()) => get_class(new PhoneDto()),
                get_class(new CarEntity()) => get_class(new CarDto())
            )
        );
        $map->addMapGeneratorElement(new EntityDtoSimpleGenerator());

        DtoClassMap::setMap($map, $map->getFlippedMap());
    }

    protected function setUp()
    {
        $isDevMode = true;

        $conn = array(
            'dbname' => $GLOBALS['DB_DBNAME'],
            'user' => $GLOBALS['DB_USER'],
            'host' => $GLOBALS['DB_HOST'],
            'password' => $GLOBALS['DB_PASSWD'],
            'driver' => 'pdo_mysql'
        );

        $paths = array(__DIR__ . '/entities');
        $config = Setup::createConfiguration($isDevMode);
        $driver = new AnnotationDriver(new AnnotationReader(), $paths);
        AnnotationRegistry::registerFile(
            __DIR__ . '/../../vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php'
        );

        AnnotationRegistry::registerLoader('class_exists');
        $config->setMetadataDriverImpl($driver);
        $config->addCustomHydrationMode('dto', 'KonstantinKuklin\DoctrineDto\Hydrator\DtoHydrator');
        $this->em = EntityManager::create($conn, $config);
    }

    public function testDtoHydratorFullJoin()
    {
        $qb = $this->em->createQueryBuilder();

        $query = $qb->select(array('u', 'up', 'p'))->from(
            'KonstantinKuklin\\Tests\\DoctrineDto\\Entity\\UserEntity',
            'u'
        )
            ->leftJoin('u.parentUser', 'up')
            ->leftJoin('u.phoneNumberList', 'p')
            ->where('u = 2')->getQuery();

        $result = $query->getResult('dto');

        $userDto = new UserDto();
        $userDto->id = 2;
        $userDto->name = 'second';
        $userDto->_isInitialized = true;
        $parentUser = clone $userDto;
        $parentUser->id = 1;
        $parentUser->name = 'first';
        $userDto->parentUser = $parentUser;

        $phoneDto1 = new PhoneDto();
        $phoneDto1->_isInitialized = true;
        $phoneDto1->id = 1;
        $phoneDto1->phone = "123";

        $phoneDto2 = new PhoneDto();
        $phoneDto2->_isInitialized = true;
        $phoneDto2->id = 2;
        $phoneDto2->phone = "234";

        $phoneDto3 = new PhoneDto();
        $phoneDto3->_isInitialized = true;
        $phoneDto3->id = 3;
        $phoneDto3->phone = "345";

        $userDto->phoneNumberList = array($phoneDto1, $phoneDto2, $phoneDto3);

        $expectedResult = array($userDto);

        self::assertEquals($expectedResult, $result);
    }

    public function testDtoHydratorWithJoin()
    {
        $qb = $this->em->createQueryBuilder();
        $query = $qb->select(array('u'))->from('KonstantinKuklin\\Tests\\DoctrineDto\\Entity\\UserEntity', 'u')
            ->where('u = 3')->getQuery();

        $result = $query->getResult('dto');

        $userDto = new UserDto();
        $userDto->id = 3;
        $userDto->name = 'third';
        $userDto->_isInitialized = true;

        self::assertEquals(array($userDto), $result);
    }

    public function testDtoHydratorManyToOne()
    {
        $qb = $this->em->createQueryBuilder();
        $query = $qb->select('c', 'IDENTITY(c.owner)')->from(
            'KonstantinKuklin\\Tests\\DoctrineDto\\Entity\\CarEntity',
            'c'
        )
            ->where('c = 1')->getQuery();

        $query->setHint(\Doctrine\ORM\Query::HINT_INCLUDE_META_COLUMNS, true);

        $result = $query->getResult('dto');

        $userDto = new UserDto();
        $userDto->id = 1;

        $carDto = new CarDto();
        $carDto->_isInitialized = true;
        $carDto->id = 1;
        $carDto->color = 'red';
        $carDto->owner = $userDto;

        self::assertEquals(array($carDto), $result);
    }

    public function testDtoHydratorWithMappedAndInversed()
    {
        $qb = $this->em->createQueryBuilder();
        $query = $qb->select('j', 'c')
            ->from('KonstantinKuklin\\Tests\\DoctrineDto\\Entity\\JobEntity', 'j')
            ->innerJoin('j.customer', 'c')
            ->where('j = 1')
            ->getQuery();

        $query->setHint(\Doctrine\ORM\Query::HINT_INCLUDE_META_COLUMNS, true);

        $result = $query->getResult('dto');

        $userDto = new UserDto();
        $userDto->id = '1';


        $jobDto = new JobDto();
        $jobDto->_isInitialized = true;
        $jobDto->id = 1;
        $jobDto->name = 'test job';
        $jobDto->customer = $userDto;

        $userDto->job = $jobDto;

        self::assertEquals(array($jobDto), $result);
    }
}