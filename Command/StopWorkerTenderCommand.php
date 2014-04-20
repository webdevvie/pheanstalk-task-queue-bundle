<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Command;

use Webdevvie\PheanstalkTaskQueueBundle\Command\AbstractWorker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webdevvie\PheanstalkTaskQueueBundle\Entity\Task;

/**
 * Class StopWorkerTenderCommand
 * Sends a signal to the worker tender to stop
 *
 * @package Webdevvie\PheanstalkTaskQueueBundle\Command
 * @author John Bakker <me@johnbakker.name>
 */
class StopWorkerTenderCommand extends AbstractWorker
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('taskqueue:stop-worker-tender')
            ->setDescription('Signals the worker tender to stop and waits for it to stop');
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
        $processes = $this->findWorkerTenderProcessesForTube($this->tube);
        if (count($processes) == 0) {
            $output->writeln("<info>Found no processes</info>");
            return;
        }
        $output->writeln('<info>Found ' . count($processes) . ' process(es)');
        foreach ($processes as $process) {
            $output->writeln('<info>sending kill to:</info> ' . $process);
            exec("kill $process");
        }
        $output->write('<info>Waiting on processes to stop</info> ');
        while (count($processes) > 0) {
            $processes = $this->findWorkerTenderProcessesForTube($this->tube);
            $output->write(".");
            sleep(1);

        }
        $output->writeln("Done!");
    }

    /**
     * Returns a list of processes that match the worker-tender
     *
     * @param string $tube
     * @return array
     */
    private function findWorkerTenderProcessesForTube($tube)
    {
        $processes = array();
        $command = "ps ax ";
        $command .= "|grep php ";
        $command .= "|grep -v stop ";
        $command .= "|grep worker-tender ";
        if ($tube !== '') {
            $command .= "|grep " . escapeshellarg('use-tube=' . $tube);
        }
        $data = exec($command);
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $line = trim($line);
            $parts = explode(" ", $line);
            if ($parts[0] === '') {
                continue;
            }
            $processes[] = intval($parts[0]);
        }

        return $processes;

    }
}
