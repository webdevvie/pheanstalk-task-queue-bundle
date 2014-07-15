<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Command;

use Webdevvie\PheanstalkTaskQueueBundle\Command\AbstractWorker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webdevvie\PheanstalkTaskQueueBundle\Entity\Task;

/**
 * Class TaskCleanupCommand
 * Cleans up tasks that are done
 *
 * @author John Bakker <me@johnbakker.name>
 */
class TaskCleanupCommand extends AbstractWorker
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('taskqueue:task-cleanup')
            ->setDescription('Cleans up any done tasks older than option supplied timeperiod')
            ->addOption(
                'period',
                null,
                InputOption::VALUE_OPTIONAL,
                'Delete anything older than supplied seconds, by default this is 1 hour',
                3600
            );
        $this->addDefaultConfiguration();
    }

    /**
     * {@inheritDoc}
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initialiseWorker($input, $output);
        $timePeriod = $input->getOption('period');
        $this->taskQueueService->cleanUpTasks($timePeriod);
    }
}
