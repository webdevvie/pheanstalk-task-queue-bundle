<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Tests\Service;

use Webdevvie\PheanstalkTaskQueueBundle\Entity\Task;
use Webdevvie\PheanstalkTaskQueueBundle\Service\DTO\WorkPackage;
use Webdevvie\PheanstalkTaskQueueBundle\Service\TaskCommandGenerator;
use Webdevvie\PheanstalkTaskQueueBundle\Command\Example\TaskDescription\ExampleTaskDescription;

/**
 * For testing the task command generator
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\Tests\Service
 * @author John Bakker <me@johnbakker.name
 */
class TaskCommandGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the generation of the correct string using an example task
     *
     * @return void
     */
    public function testGenerateCommand()
    {
        $exampleTask = new ExampleTaskDescription();
        $exampleTask->message = 'test';
        $exampleTask->wait = 3;
        $job = new \Pheanstalk_Job(1, '666');
        $task = new Task($exampleTask, '666', 'testtube');

        $workPackage = new WorkPackage($task, $job, $exampleTask);


        $generator = new TaskCommandGenerator();
        $output = $generator->generate($workPackage);
        $expected = "'taskqueue:example-task-worker' '3' --message='test'";
        $this->assertEquals(
            $expected,
            $output,
            'Example task with option and argument result in the following values'
        );
    }
}
