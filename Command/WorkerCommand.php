<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Command;


use JMS\Serializer\SerializerBuilder;
use Webdevvie\PheanstalkTaskQueueBundle\Command\AbstractWorker;
use Webdevvie\PheanstalkTaskQueueBundle\Service\DTO\WorkPackage;
use Webdevvie\PheanstalkTaskQueueBundle\Service\Exception\TaskCommandGeneratorException;
use Webdevvie\PheanstalkTaskQueueBundle\Service\Exception\TaskQueueServiceException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Webdevvie\PheanstalkTaskQueueBundle\Entity\Task;
use Webdevvie\PheanstalkTaskQueueBundle\Service\TaskCommandGenerator;

/**
 * Class WorkerCommand
 * Picks up tasks from the taskqueue service and starts them in a child process
 * This command is used by the worker-tender (WorkerTenderCommand.php).
 * This command outputs child output and outputs responses the ChildProcessContainer class
 * can understand
 *
 * @author John Bakker <me@johnbakker.name>
 */
class WorkerCommand extends AbstractWorker
{
    /**
     * @var \Webdevvie\PheanstalkTaskQueueBundle\Service\TaskCommandGenerator;
     */
    private $taskCommandGenerator;

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('taskqueue:task-worker')
            ->setDescription('Runs the task queue');
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
        $this->taskCommandGenerator = $this->getContainer()->get(
            'webdevvie_pheanstalk_taskqueue.task_command_generator'
        );
        $this->verboseOutput("<info>Taskworker observing tube:</info>" . $this->tube);
        while ($this->keepWorking) {
            try {
                $taskObject = $this->taskQueueService->reserveTask($this->tube);
            } catch (TaskQueueServiceException $exception) {
                $this->verboseOutput("<error>Exception from service:</error>" . $exception->getMessage());
                $this->logException($exception);
                $taskObject = false;
            }
            if ($taskObject instanceof WorkPackage) {
                $this->runTask($taskObject);
            } elseif (is_object($taskObject)) {
                $this->verboseOutput("Ignoring type:" . get_class($taskObject));
            }
            if ($this->shutdownNow || $this->shutdownGracefully) {
                $this->keepWorking = false;
            }
        }
    }

    /**
     * @param WorkPackage $taskObject
     * @return void
     */
    private function runTask(WorkPackage $taskObject)
    {
        try {
            $command = $this->taskCommandGenerator->generate($taskObject);
        } catch (TaskCommandGeneratorException $exception) {
            $logMessage = "Invalid command supplied in " . get_class($taskObject);
            $this->verboseOutput($logMessage);
            $this->taskQueueService->markFailed($taskObject, $logMessage);
            return;
        }
        $fullCommand = 'exec app/console ' . $command;
        $this->verboseOutput("<info>[[CHILD::BUSY]]:</info>" . $command);
        $process = new Process($fullCommand, $this->consolePath, null, null, null);
        $process->start();
        $failed = false;
        $runningCommand = true;
        $hasReceivedSignal = false;
        $exitCode = 0;
        $exitCodeText = '';
        $childOutput = '';
        while ($runningCommand) {
            $this->handleRunningProcess(
                $process,
                $runningCommand,
                $failed,
                $hasReceivedSignal,
                $exitCode,
                $exitCodeText
            );
            $incOut = $process->getIncrementalOutput();
            $childOutput .= $incOut;
            if (strlen($incOut) > 0) {
                $this->output->write($incOut);
            }
        }
        if ($failed) {
            $this->verboseOutput("FAILED CHILD: " . $exitCode . "::" . $exitCodeText);
            $this->taskQueueService->markFailed($taskObject, $childOutput);
        } else {
            $this->taskQueueService->markDone($taskObject, $childOutput);
        }
        if ($process->isRunning()) {
            $process->stop();
        }
        $this->verboseOutput("<info>[[CHILD::READY]]:</info>");
    }

    /**
     * Handles any running process waiting for signals..
     *
     * @param Process $process
     * @param boolean &$runningCommand
     * @param boolean &$failed
     * @param boolean &$hasReceivedSignal
     * @param integer &$exitCode
     * @param string &$exitCodeText
     * @return void
     */
    private function handleRunningProcess(
        Process $process,
        &$runningCommand,
        &$failed,
        &$hasReceivedSignal,
        &$exitCode,
        &$exitCodeText
    ) {
        if ($process->isRunning()) {
            if ($hasReceivedSignal) {
                return;
            }
            //force it to shut down now!
            if ($this->shutdownNow) {
                $this->output->write("[RECEIVED SIGNAL] Shutting down now!");
                $process->stop(0);
                $hasReceivedSignal = true;
                $this->keepWorking = false;
                $runningCommand = false;
            }
            if ((!$this->keepWorking || $this->shutdownGracefully)) {
                //we need to keep this running until we are finished...
                $this->output->write("[RECEIVED SIGNAL] will shutdown after finishing task");
                $hasReceivedSignal = true;
            }
        } else {
            $runningCommand = false;
            $exitCode = $process->getExitCode();
            $exitCodeText = $process->getExitCode();
            if ($exitCode != 0) {
                $failed = true;
            }
        }
    }
}
