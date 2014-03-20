<?php

namespace Webdevvie\TaskQueueBundle\Tests\Service;


use JMS\Serializer\SerializerBuilder;
use Webdevvie\TaskQueueBundle\Service\DTO\WorkPackage;
use Webdevvie\TaskQueueBundle\Command\Example\TaskDescription\ExampleTaskDescription;
use Symfony\Component\Serializer\Serializer;
use Webdevvie\TaskQueueBundle\Entity\Task;
use Webdevvie\TaskQueueBundle\Entity\TaskRepository;
use Webdevvie\TaskQueueBundle\Service\TaskQueueService;
use Mockery;

/**
 * For testing the task queue service
 * @package Webdevvie\TaskQueueBundle\Tests\Service
 * @author John Bakker <me@johnbakker.name
 */
class TaskQueueServiceTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     * @var \Mockery\MockInterface
     */
    public $pheanStalkProxy;

    /**
     * @var \Mockery\MockInterface
     */
    public $entityManager;
    /**
     * @var array
     */
    public $params;

    /**
     * @var \Mockery\MockInterface
     */
    public $taskRepository;

    /**
     * @var Serializer
     */
    public $serializer;

    /**
     * Set up all the mocks and the serializer class
     *
     * @return void
     */
    public function setUp()
    {
        $this->serializer = SerializerBuilder::create()->build();
        $this->setupPheanstalkProxyMock();
        $this->generateFakeParams();
        $this->setupEntityManagerMock();
    }

    /**
     * Test the queueing of tasks
     *
     * @return void
     */
    public function testQueueTask()
    {
        $this->entityManager->shouldReceive('persist');
        $this->entityManager->shouldReceive('flush');
        $this->pheanStalkProxy->shouldReceive('useTube')->andReturn($this->pheanStalkProxy);
        $this->pheanStalkProxy->shouldReceive('put');
        $service = new TaskQueueService(
            $this->entityManager,
            $this->pheanStalkProxy,
            $this->serializer,
            $this->params
        );
        $exampleTask = new ExampleTaskDescription();
        $exampleTask->message = 'new task is new';
        $service->queueTask($exampleTask);
    }

    /**
     * tests the status update call in the service
     *
     * @return void
     */
    public function testMarkDone()
    {
        $exampleTask = new ExampleTaskDescription();
        $stringVersion = get_class($exampleTask) . "::" . $this->serializer->serialize($exampleTask, 'json');
        $taskEntity = new Task($exampleTask, $stringVersion, 'gtldtube');

        $job = new \Pheanstalk_Job(1, 123);
        $workPackage = new WorkPackage($taskEntity, $job, $exampleTask);

        $this->entityManager->shouldReceive('persist')->withArgs([$taskEntity]);
        $this->entityManager->shouldReceive('flush');
        $this->pheanStalkProxy->shouldReceive('delete');
        $service = new TaskQueueService(
            $this->entityManager,
            $this->pheanStalkProxy,
            $this->serializer,
            $this->params
        );
        $service->markDone($workPackage);
    }
    /**
     * tests the status update call in the service
     *
     * @return void
     */
    public function testMarkFailed()
    {
        $exampleTask = new ExampleTaskDescription();
        $stringVersion = get_class($exampleTask) . "::" . $this->serializer->serialize($exampleTask, 'json');
        $taskEntity = new Task($exampleTask, $stringVersion, 'gtldtube');

        $job = new \Pheanstalk_Job(1, 123);

        $workPackage = new WorkPackage($taskEntity, $job, $exampleTask);

        $this->entityManager->shouldReceive('persist')->withArgs([$taskEntity]);
        $this->entityManager->shouldReceive('flush');
        $this->pheanStalkProxy->shouldReceive('delete');
        $service = new TaskQueueService(
            $this->entityManager,
            $this->pheanStalkProxy,
            $this->serializer,
            $this->params
        );
        $service->markDone($workPackage);
    }

    /**
     * tests the recovery of an element
     *
     * @return void
     */
    public function testRegenerate()
    {
        $id = 1;



        $exampleTask = new ExampleTaskDescription();

        $stringVersion = get_class($exampleTask) . "::" . $this->serializer->serialize($exampleTask, 'json');

        $taskEntity = new Task($exampleTask, $stringVersion, 'gtldtube');
        $taskEntity->setStatus(Task::STATUS_FAILED);


        $this->taskRepository->shouldReceive('find')->withArgs([$id])->andReturn($taskEntity);
        $this->entityManager->shouldReceive('flush');
        $this->pheanStalkProxy->shouldReceive('useTube')->andReturn($this->pheanStalkProxy);
        $this->pheanStalkProxy->shouldReceive('put');
        $service = new TaskQueueService(
            $this->entityManager,
            $this->pheanStalkProxy,
            $this->serializer,
            $this->params
        );
        $response = $service->regenerateTask($id);
        $this->assertTrue($response, 'Response from regenerateTask is not true');
    }

    /**
     * tests the reserving of tasks if nothing is in the queue
     *
     * @return void
     */
    public function testReserveEmpty()
    {
        $this->setupNothingInQueue();
        $service = new TaskQueueService(
            $this->entityManager,
            $this->pheanStalkProxy,
            $this->serializer,
            $this->params
        );
        $task = $service->reserveTask();
        $this->assertNull($task, 'The task is not null');
    }


    /**
     * tests the reserving of tasks if nothing is in the queue
     *
     * @return void
     */
    public function testReserveFilled()
    {

        $exampleTask = new ExampleTaskDescription();
        $taskId = 1;
        $this->setupExampleTaskInQueue($exampleTask, $taskId);
        $service = new TaskQueueService(
            $this->entityManager,
            $this->pheanStalkProxy,
            $this->serializer,
            $this->params
        );
        $workPackage = $service->reserveTask();
        $this->assertInstanceOf(
            '\Webdevvie\TaskQueueBundle\Service\DTO\WorkPackage',
            $workPackage,
            'Received class is not of class WorkPackage'
        );

        $this->assertInstanceOf(
            '\Webdevvie\TaskQueueBundle\Command\Example\TaskDescription\ExampleTaskDescription',
            $workPackage->getTaskDescription(),
            'Received class is not of class ExampleTask'
        );
        $this->assertInstanceOf(
            '\Webdevvie\TaskQueueBundle\Entity\Task',
            $workPackage->getTaskEntity(),
            'Received class is not of class Task'
        );
    }



    /**
     * Sets up the example task into the (mocked) queue and in the (mocked) database
     *
     * @param ExampleTaskDescription $exampleTask
     * @param integer $taskId
     * @return void
     */
    public function setupExampleTaskInQueue(ExampleTaskDescription $exampleTask, $taskId = 1)
    {

        $exampleTask->taskIdentifier = $taskId;
        $stringVersion = get_class($exampleTask) . "::" . $this->serializer->serialize($exampleTask, 'json');
        $taskEntity = new Task($exampleTask, $stringVersion, 'gtldtube');
        $this->taskRepository->shouldReceive('find')->withArgs([1])->andReturn($taskEntity);
        $job = \Mockery::mock("\Pheanstalk_Job");
        $job->shouldIgnoreMissing();
        $job->shouldReceive('getData')
            ->andReturn($stringVersion);
        $this->pheanStalkProxy->shouldReceive('watch')
            ->andReturn($this->pheanStalkProxy);
        $this->pheanStalkProxy->shouldReceive('ignore')
            ->andReturn($this->pheanStalkProxy);
        $this->pheanStalkProxy->shouldReceive('reserve')
            ->andReturn($job);
        $this->entityManager->shouldReceive('persist')->withArgs([$taskEntity]);
        $this->entityManager->shouldReceive('flush');

    }

    /**
     * Sets up an empty queue
     *
     * @return void
     */
    public function setupNothingInQueue()
    {

        $this->pheanStalkProxy->shouldReceive('watch')
            ->andReturn($this->pheanStalkProxy);
        $this->pheanStalkProxy->shouldReceive('ignore')
            ->andReturn($this->pheanStalkProxy);
        $this->pheanStalkProxy->shouldReceive('reserve')
            ->andReturn(false);
    }

    /**
     * Sets up an EntityManager mock and the taskrepository mock to be returned
     *
     * @return void
     */
    public function setupEntityManagerMock()
    {
        $this->entityManager = \Mockery::mock('\Doctrine\ORM\EntityManager');
        $this->setupTaskRepository();
        $this->entityManager->shouldReceive('getRepository')->once()->andReturn($this->taskRepository);
    }

    /**
     * Sets up the taskrepository
     *
     * @return void
     */
    public function setupTaskRepository()
    {
        $this->taskRepository = \Mockery::mock('\Webdevvie\TaskQueueBundle\Entity\TaskRepository');
    }

    /**
     * Sets up the pheanstalk proxy mock
     *
     * @return void
     */
    public function setupPheanstalkProxyMock()
    {
        $this->pheanStalkProxy = \Mockery::mock('\Leezy\PheanstalkBundle\Proxy\PheanstalkProxy');
    }

    /**
     * Generates some fake params
     *
     * @return void
     */
    public function generateFakeParams()
    {
        $this->params = ["default_tube" => "gtldtube"];
    }
}
