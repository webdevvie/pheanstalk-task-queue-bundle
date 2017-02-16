<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Command;

use Webdevvie\PheanstalkTaskQueueBundle\Service\Exception\TaskQueueServiceException;
use Webdevvie\PheanstalkTaskQueueBundle\Command\AbstractWorker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RegenerateFailedTaskCommand
 * command for regenerating failed task from the table
 *
 * @author John Bakker <me@johnbakker.name>
 */
class RegenerateFailedTaskCommand extends AbstractWorker
{

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('taskqueue:regenerate-failed-task')
            ->setDescription('Regenerates failed command of specific type');
        $this->addDefaultConfiguration();
        $this->addOption(
            'id',
            null,
            InputOption::VALUE_OPTIONAL,
            'Regenerate a specific task. Can also be comma separated string, then you can supply multiple ids'
        );
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
        $id = $input->getOption("id");
        $identifier = intval($id);
        if (strstr($id, ",")) {
            $ids = explode(",", $id);
            foreach ($ids as $identifier) {
                if (intval($identifier) > 0) {
                    $this->regenerateTaskById($identifier);
                }
            }
        } elseif ($identifier > 0) {
            $this->regenerateTaskById(intval($id));
        }
    }

    /**
     * Regenerates a task that has failed
     *
     * @param integer $id
     * @return void
     */
    private function regenerateTaskById($id)
    {
        try {
            $response = $this->taskQueueService->regenerateTask($id);
            if ($response) {
                $this->output->writeln("<info>Regeneration succes</info>");
            } else {
                $this->output->writeln("<error>Regeneration succes</error>");
            }
        } catch (TaskQueueServiceException $e) {
            $this->output->writeln("<error>Error!:</error>" . $e->getMessage());
        }
    }
}
