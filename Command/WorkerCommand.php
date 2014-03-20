<?php

namespace Webdevvie\TaskQueueBundle\Command;


use JMS\Serializer\SerializerBuilder;
use Webdevvie\TaskQueueBundle\Command\AbstractWorker;
use Webdevvie\TaskQueueBundle\Service\DTO\WorkPackage;
use Webdevvie\TaskQueueBundle\Service\Exception\TaskCommandGeneratorException;
use Webdevvie\TaskQueueBundle\Service\Exception\TaskQueueServiceException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Webdevvie\TaskQueueBundle\Entity\Task;
use Webdevvie\TaskQueueBundle\Service\TaskCommandGenerator;

/**
 * Class WorkerCommand
 * Picks up tasks from the taskqueue service and starts them in a child process
 * This command is used by the worker-tender (WorkerTenderCommand.php).
 * This command outputs child output and outputs responses the ChildProcessContainer class
 * can understand
 *
 * @author John Bakker <me@johnbakker.name
 */
class WorkerCommand extends AbstractWorker
{
    /**
     * @var \Webdevvie\TaskQueueBundle\Service\TaskCommandGenerator;
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
        $this->taskCommandGenerator = $this->getContainer()->get('webdevvie_taskqueue.task_command_generator');
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
            if ($this->shutdownNow) {
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
            $this->verboseOutput("Invalid command supplied in " . get_class($taskObject));
            $this->taskQueueService->markFailed($taskObject);
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
        while ($runningCommand) {
            $this->handleRunningProcess(
                $process,
                $taskObject,
                $runningCommand,
                $failed,
                $hasReceivedSignal,
                $exitCode,
                $exitCodeText
            );
            $incOut = $process->getIncrementalOutput();
            if (strlen($incOut)>0) {
                $this->output->write($incOut);
            }
        }
        if ($failed) {
            $this->verboseOutput("FAILED CHILD: ". $exitCode . "::" . $exitCodeText);
            $this->taskQueueService->markFailed($taskObject);
        } else {
            $this->taskQueueService->markDone($taskObject);
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
     * @param WorkPackage $taskObject
     * @param boolean &$runningCommand
     * @param boolean &$failed
     * @param boolean &$hasReceivedSignal
     * @param integer &$exitCode
     * @param string &$exitCodeText
     * @return void
     */
    private function handleRunningProcess(
        Process $process,
        WorkPackage $taskObject,
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
                $this->keepWorking=false;
                $runningCommand=false;
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
