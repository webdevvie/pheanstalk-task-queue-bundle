<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Test\Service;

use Webdevvie\PheanstalkTaskQueueBundle\Service\PheanstalkConnection;
use Mockery;
use Webdevvie\PheanstalkTaskQueueBundle\Service\PheanstalkFactory;

/**
 * Class PheanstalkConnectionTest
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\Tests\Service
 */
class PheanstalkConnectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Mockery\MockInterface
     */
    private $pheanstalk;

    /**
     * @var PheanstalkConnection
     */
    private $connection;

    public function setUp()
    {
        $this->pheanstalk = Mockery::mock('\Pheanstalk_Pheanstalk');

        $pheanstalkFactory = Mockery::mock('Webdevvie\PheanstalkTaskQueueBundle\Service\PheanstalkFactory');
        $pheanstalkFactory->shouldReceive('create')->with('localhost')->andReturn($this->pheanstalk)->once();
        $this->pheanstalk->shouldReceive('useTube')->with('defaulttube')->once();
        $this->connection = new PheanstalkConnection($pheanstalkFactory, 'localhost', 'defaulttube');
    }

    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * @return void
     */
    public function testIfPutWrapsProperly()
    {
        $this->pheanstalk->shouldReceive('put')->withArgs(array('data', 1, 2, 3))->once();
        $return = $this->connection->put('data', 1, 2, 3);
        $this->assertEquals($this->connection, $return, 'Return should be the same as the connection');
    }

    /**
     * @return void
     */
    public function testIfDeleteWrapsProperly()
    {
        $job = Mockery::mock('\Pheanstalk_Job');
        $this->pheanstalk->shouldReceive('delete')->with($job)->once();
        $this->connection->delete($job);
    }

    /**
     * @return void
     */
    public function testIfWatchWrapsProperly()
    {
        $this->pheanstalk->shouldReceive('watch')->with('tube')->once();
        $return = $this->connection->watch('tube');
        $this->assertEquals($this->connection, $return, 'Return should be the same as the connection');
    }

    /**
     * @return void
     */
    public function testIfReserveWrapsProperly()
    {
        $this->pheanstalk->shouldReceive('reserve')->with(1)->andReturn(false)->once();
        $return = $this->connection->reserve(1);
        $this->assertFalse($return, 'Return should be false');
    }

    /**
     * @return void
     */
    public function testIfIgnoreWrapsProperly()
    {
        $this->pheanstalk->shouldReceive('ignore')->with('tube')->once();
        $return = $this->connection->ignore('tube');
        $this->assertEquals($this->connection, $return, 'Return should be the same as the connection');
    }

    /**
     * @return void
     */
    public function testIfUseTubeWrapsProperly()
    {
        $this->pheanstalk->shouldReceive('useTube')->with('tube')->once();
        $return = $this->connection->useTube('tube');
        $this->assertEquals($this->connection, $return, 'Return should be the same as the connection');
    }
}
