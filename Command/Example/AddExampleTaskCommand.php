<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Command\Example;


use Webdevvie\PheanstalkTaskQueueBundle\Command\AbstractWorker;
use Webdevvie\PheanstalkTaskQueueBundle\Command\Example\TaskDescription\ExampleTaskDescription;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AddExampleTaskCommand
 * Adds example work to the queue
 *
 * @package Webdevvie\TldBundle\Command
 * @author John Bakker <me@johnbakker.name
 */
class AddExampleTaskCommand extends AbstractWorker
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('taskqueue:add-example-task')
            ->setDescription('adds an example task to the beanstalk tube');
        $this->addOption('total-tasks', null, InputOption::VALUE_OPTIONAL, 'How many tasks to add', 1);
        $this->addDefaultConfiguration();
    }

    /**
     * Adds an example task to the task queue using the ExampleTask.php
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initialiseWorker($input, $output);
        $totalTasks = $input->getOption('total-tasks');
        for ($counter = 0; $counter < $totalTasks; $counter++) {
            $task = new ExampleTaskDescription();
            $task->message = 'hello world';
            $task->wait = 1;
            $this->taskQueueService->queueTask($task, $this->tube);
        }
    }
}
