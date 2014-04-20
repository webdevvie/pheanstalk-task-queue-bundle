<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Command\Tender;

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use JMS\Serializer\Serializer;
use Webdevvie\PheanstalkTaskQueueBundle\TaskDescription\TaskDescriptionInterface;
use Webdevvie\PheanstalkTaskQueueBundle\Service\Exception\TaskQueueServiceException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Process;
use Webdevvie\PheanstalkTaskQueueBundle\Command\WorkerTenderCommand;
use Webdevvie\PheanstalkTaskQueueBundle\Command\AbstractWorker;

/**
 * Class ChildProcessContainer
 * A child process container that handles starting of the a worker, sending signal and reading
 * status data back from the worker child process.
 *
 * @author John Bakker <me@johnbakker.name>
 */
class ChildProcessContainer
{
    const STATUS_UNBORN = 'unborn';
    const STATUS_ALIVE = 'alive';
    const STATUS_SLEEPY = 'sleepy';
    const STATUS_BUSY = 'busy';
    const STATUS_READY = 'ready';
    const STATUS_DEAD = 'dead';
    const STATUS_CLEANEDUP = 'cleanedup';
    const STATUS_CLEANINGUP = 'cleaningup';
    const STATUS_BUSY_BUT_SLEEPY = 'busybutsleepy';

    /**
     * The path to the console file
     * @var string
     */
    private $consolePath;

    /**
     * The command used for the workers
     * @var string
     */
    private $workerCommand;

    /**
     * The beanstalk tube to use for the child
     * @var string
     */
    private $tube;

    /**
     * The current status of the child
     * @var string
     */
    public $status = ChildProcessContainer::STATUS_UNBORN;

    /**
     * @var Process
     */
    private $process;

    /**
     * @var WorkerTenderCommand
     */
    private $parent;

    /**
     * A temporary output buffer for the child
     * @var string
     */
    private $outputBuffer = '';

    /**
     * A temporary output buffer for the child's errors
     * @var string
     */
    private $errorOutputBuffer = '';

    /**
     * The current used bufferlength
     * @var integer
     */
    private $bufferLength = 0;

    /**
     * The time the process started
     * @var integer
     */
    private $startTime = 0;

    /**
     * To mark whether this child has already received a hangup signal or not
     * @var boolean
     */
    private $hasReceivedHUP = false;

    /**
     * Constructs the class
     *
     * @param string $consolePath
     * @param string $workerCommand
     * @param string $tube
     * @param AbstractWorker $parent
     */
    public function __construct($consolePath, $workerCommand, $tube, AbstractWorker $parent)
    {
        $this->consolePath = $consolePath;
        $this->workerCommand = $workerCommand;
        $this->tube = $tube;
        $this->parent = $parent;
    }

    /**
     * Returns the age of the process in seconds
     *
     * @return integer
     */
    public function getAge()
    {
        return time() - $this->startTime;
    }

    /**
     * returns the total length of the buffer in the process
     *
     * @return integer
     */
    public function getBufferLength()
    {
        return $this->bufferLength;
    }

    /**
     * Checks the health of this child and returns true if healthy otherwise marks it for cleanup
     *
     * @return boolean
     */
    public function checkHealth()
    {
        if (!$this->process->isRunning()) {
            if ($this->status == self::STATUS_CLEANINGUP || $this->status == self::STATUS_CLEANEDUP) {
                //okay so we're ready to stop!
                $this->status = self::STATUS_DEAD;
                return true;
            } elseif ($this->status == ChildProcessContainer::STATUS_SLEEPY) {
                //okay so we're ready to stop!
                $this->status = self::STATUS_DEAD;
                return true;
            }
            return false;
        } else {
            return true;
        }

    }

    /**
     * Returns if true if the process is dead
     *
     * @return boolean
     */
    public function isDead()
    {
        return ($this->status == self::STATUS_DEAD);
    }

    /**
     * Cleans up a process
     *
     * @return void
     */
    public function cleanUp()
    {
        $this->status = self::STATUS_CLEANINGUP;
        if ($this->process->isRunning()) {
            $this->sendTERMSignal();
        } else {
            $this->status = self::STATUS_CLEANEDUP;
        }

    }

    /**
     * Returns true if the process is cleaned up
     *
     * @return boolean
     */
    public function isCleanedUp()
    {
        return ($this->status == self::STATUS_CLEANEDUP);
    }

    /**
     * Returns the currently available output lines
     *
     * @return array
     */
    public function getOutputLinesAvailable()
    {
        $outputLines = array();
        return $outputLines;
    }

    /**
     * Looks into and processes the child's output
     *
     * @param string $childName
     * @return void
     */
    public function processOutput($childName)
    {
        $output = $this->outputBuffer . $this->process->getIncrementalOutput();
        $errorOutput = $this->errorOutputBuffer . $this->process->getIncrementalErrorOutput();
        $outputLines = explode("\n", $output);
        $errorOutputLines = explode("\n", $errorOutput);
        if (count($outputLines) > 0) {
            $lastItem = array_pop($outputLines);
            $this->outputBuffer = $lastItem;
            $this->bufferLength += implode("\n", $outputLines);
            foreach ($outputLines as $line) {
                if (strstr($line, "[[CHILD::BUSY]]")) {
                    $this->parent->verboseOutput('<info>' . $childName . ' BUSY</info>');
                    $this->status = ChildProcessContainer::STATUS_BUSY;
                } elseif (strstr($line, "[[CHILD::READY]]")) {
                    $this->parent->verboseOutput('<info>' . $childName . ' READY</info>');
                    if ($this->status != ChildProcessContainer::STATUS_BUSY_BUT_SLEEPY) {
                        $this->status = ChildProcessContainer::STATUS_READY;
                    } else {
                        $this->status = ChildProcessContainer::STATUS_SLEEPY;
                    }
                } elseif (strlen($line) > 0) {
                    $this->parent->verboseOutput('<info>OUTPUT ' . $childName . ':</info>' . $line);
                }
            }
        }
        if (count($errorOutputLines) > 0) {
            $lastItemError = array_pop($errorOutputLines);
            $this->errorOutputBuffer = $lastItemError;
            $knownErrorOutput = implode("\n", $errorOutputLines);
            $this->bufferLength += strlen($knownErrorOutput);
        }
    }

    /**
     * Starts the child process
     *
     * @return void
     */
    public function start()
    {
        $cmd = 'app/console ' . $this->workerCommand;
        if ($this->tube != '') {
            $cmd .= ' --use-tube=' . escapeshellarg($this->tube);
        }
        if ($this->parent->isVerbose()) {
            $cmd .= ' --verbose';
        }
        $this->startTime = time();
        $this->parent->verboseOutput('<info>Starting:</info>' . $cmd);
        $this->process = new Process('exec ' . $cmd, $this->consolePath, null, null, null);
        $this->process->start();
        if ($this->process->isRunning()) {
            $this->status = ChildProcessContainer::STATUS_ALIVE;
        } else {
            $this->status = ChildProcessContainer::STATUS_DEAD;
        }
    }

    /**
     * Tells the child to not pick up any more work and go to bed
     *
     * @return void
     */
    public function bedtime()
    {
        $this->status = ChildProcessContainer::STATUS_SLEEPY;
        $this->sendTERMSignal();
    }

    /**
     * Sends a signal to the process, this method prevents the signal from being sent twice
     *
     * @return void
     */
    private function sendTERMSignal()
    {
        if (!$this->hasReceivedHUP) {
            $this->hasReceivedHUP = true;
            try {
                $this->process->signal(SIGTERM);
            } catch (\Symfony\Component\Process\Exception\LogicException $e) {
                //In case the process ends between checking and actually sending the signal
            }
        }
    }

    /**
     * Tells the child to not pick up any more work and go to bed
     *
     * @return void
     */
    public function getReadyForBed()
    {
        $busyStatusses = array(ChildProcessContainer::STATUS_SLEEPY,
            ChildProcessContainer::STATUS_BUSY_BUT_SLEEPY
        );
        if (in_array($this->status, $busyStatusses)) {
            //ready for bed
            return;
        }
        if ($this->status == ChildProcessContainer::STATUS_BUSY) {
            $this->status = ChildProcessContainer::STATUS_BUSY_BUT_SLEEPY;
        } else {
            $this->status = ChildProcessContainer::STATUS_SLEEPY;
        }
        if ($this->process->isRunning()) {
            $this->sendTERMSignal();
        }
    }
}
