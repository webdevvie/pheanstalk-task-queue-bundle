<?php

namespace Webdevvie\TaskQueueBundle\Command\Example;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExampleWorkerCommand
 * Example worker command
 *
 * @package Webdevvie\TldBundle\Command
 * @author John Bakker <me@johnbakker.name
 */
class ExampleWorkerCommand extends ContainerAwareCommand
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('taskqueue:example-task-worker')
            ->setDescription('an example worker for a task queue')
            ->addArgument('wait', InputArgument::REQUIRED, 'The ammount of seconds to wait before printing the message')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'What message do you want to reply with');
    }

    /**
     * {@inheritDoc}
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $message = $input->getOption('message');
        $output->write("I HAVE A MESSAGE FOR YOU!");
        $wait = intval($input->getArgument('wait'));
        for ($counter = 0; $counter < $wait; $counter++) {
            $output->write(".");
            sleep(1);
        }
        $output->writeLn($message);
    }
}
