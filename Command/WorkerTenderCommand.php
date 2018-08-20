<?php

namespace Webdevvie\PheanstalkTaskQueueBundle\Command;


use JMS\Serializer\SerializerBuilder;
use Webdevvie\PheanstalkTaskQueueBundle\Command\AbstractWorker;
use Webdevvie\PheanstalkTaskQueueBundle\Command\Tender\ChildProcessContainer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class WorkerTenderCommand
 * This command takes care of starting and maintaining enough workers. This is designed to be run
 * one time per "tube"* You can supply several parameters to this command. Please refer to those parameters
 * for more information about customising your worker-tender experience
 *
 * @author John Bakker <me@johnbakker.name>
 */
class WorkerTenderCommand extends AbstractWorker
{
    /**
     * Contains all the child processes
     * @var array
     */
    private $family = array();

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('taskqueue:task-worker-tender')
            ->setDescription('tends,breeds and murders workers')
            ->addOption(
                'worker-command',
                null,
                InputOption::VALUE_OPTIONAL,
                'the worker command to use',
                'taskqueue:task-worker'
            )
            ->addOption(
                'min-workers',
                null,
                InputOption::VALUE_OPTIONAL,
                'Minimal amount of workers to have running',
                2
            )
            ->addOption('spare-workers', null, InputOption::VALUE_OPTIONAL, 'Amount of workers to keep spooled up', 1)
            ->addOption('max-workers', null, InputOption::VALUE_OPTIONAL, 'Amount of workers to keep', 5)
            ->addOption(
                'max-worker-age',
                null,
                InputOption::VALUE_OPTIONAL,
                'Max time to keep a worker in seconds',
                600
            )->addOption(
                'max-worker-buffer',
                null,
                InputOption::VALUE_OPTIONAL,
                'Max time to keep a worker in seconds',
                6000000
            );
        $this->addOption('use-tube', null, InputOption::VALUE_REQUIRED, 'What tube to work with');
    }

    /**
     * {@inheritDoc}
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->initialiseWorker($input, $output);
        $this->verboseOutput("<info>Starting up</info>");
        while ($this->keepWorking) {
            //do stuff
            //check if the processes are still working
            foreach ($this->family as $key => &$child) {
                $this->handle($key, $child);
            }
            $this->familyPlanning();
            //go to sleep for a bit
            usleep(100000);
            pcntl_signal_dispatch()
        }
        $this->verboseOutput("<info>DONE!</info>");
    }

    /**
     * Handles all child communication things
     *
     * @param string $key
     * @param ChildProcessContainer $child
     * @return void
     */
    private function handle($key, ChildProcessContainer $child)
    {
        $tube = $this->tube;
        $child->processOutput('child_' . $tube . $key);
        //send unhealthy child processes to bed (kill em off)
        if (!$child->checkHealth()) {
            $child->getReadyForBed();
        }
        //send old children to bed (kill em off)
        $readyStatusses = array(ChildProcessContainer::STATUS_READY, ChildProcessContainer::STATUS_ALIVE);
        if ($child->getAge() >= $this->input->getOption('max-worker-age') &&
            in_array($child->status, $readyStatusses)
        ) {
            $child->bedtime();
        }
        //Kill off processes that have used up too much memory already
        if ($child->getBufferLength() >= $this->input->getOption('max-worker-buffer') &&
            in_array($child->status, $readyStatusses)
        ) {
            $child->bedtime();
        }
        //if the child is dead ,clean up the mess
        if ($child->isDead()) {
            $child->cleanUp();
        }
        //if the child is cleaned up remove the object and clean up some memory
        if ($child->isCleanedUp()) {
            $this->verboseOutput("<info>Unsetting Child</info>: $key");
            unset($this->family[$key]);
            $this->verboseOutput("<info>Children left</info>: " . count($this->family));
        }
    }

    /**
     * Produce more hands for the work needed
     *
     * @return void
     */
    private function familyPlanning()
    {
        list($total, $available) = $this->countWorkers();
        if ($this->shutdownGracefully) {
            if ($total > 0) {
                $this->verboseOutput("<info>Shutting down</info> $total remaining children");
                foreach ($this->family as $cnr => $child) {
                    $this->verboseOutput("<info>Child: $cnr </info>" . $child->status);
                }
                $this->tellChildrenToPrepareForBed();

            } else {
                $this->keepWorking = false;
            }
            return;
        }
        list($shouldHaveLessWorkers, $shouldHaveMoreWorkers) = $this->moreOrLess($total, $available);
        if ($shouldHaveMoreWorkers) {
            $this->family[] = $this->spawnChild();
        }
        if ($shouldHaveLessWorkers) {
            //find one of the family members not doing anything and cap it!
            if ($this->findDisposableWorkers()) {
                //we found one
                $this->verboseOutput("<info>Found a disposable worker</info>");
            }
        }
    }

    /**
     * Returns an array with the worker counts
     *
     * @return array
     */
    public function countWorkers()
    {
        $total = count($this->family);
        $busy = 0;
        $available = 0;
        foreach ($this->family as &$child) {
            switch ($child->status) {
                case ChildProcessContainer::STATUS_ALIVE:
                case ChildProcessContainer::STATUS_READY:
                    $available++;
                    break;
                case ChildProcessContainer::STATUS_BUSY:
                case ChildProcessContainer::STATUS_BUSY_BUT_SLEEPY:
                    $busy++;
                    break;
            }
        }
        return array($total, $available);
    }

    /**
     * Returns two booleans if there should be more or less workers
     *
     * @param integer $total
     * @param integer $busy
     * @param integer $available
     * @return array
     */
    public function moreOrLess($total, $available)
    {
        $shouldHaveMoreWorkers = false;
        $shouldHaveLessWorkers = false;
        $minWorkers = $this->input->getOption('min-workers');
        $maxWorkers = $this->input->getOption('max-workers');
        $spareWorkers = $this->input->getOption('spare-workers');
        if ($total < $minWorkers) {
            //too little workers,spawn more please
            $shouldHaveMoreWorkers = true;
            $this->verboseOutput("<info>Too little workers</info>: $total / $minWorkers");
        } elseif (($available < $spareWorkers) && ($total <= $maxWorkers)) {
            //we have not enough spare workers we should spawn some more... unless we have reached the max workers limit
            $shouldHaveMoreWorkers = true;
            $this->verboseOutput("<info>Too little spare workers</info>: $available / $spareWorkers");
        } elseif ($total >= $maxWorkers) {
            //we should have less workers because we somehow ended up over the limit
            $shouldHaveLessWorkers = true;
        } elseif ($available >= $minWorkers + $spareWorkers && !$shouldHaveMoreWorkers) {
            //we have more than enough workers for the amount of work we should probably kill one off.
            $shouldHaveLessWorkers = true;
        }
        return array($shouldHaveLessWorkers, $shouldHaveMoreWorkers);
    }

    /**
     * Finds disposable workers, if it finds them it returns true
     *
     * @return boolean
     */
    private function findDisposableWorkers()
    {
        $disposableStatusses = array(ChildProcessContainer::STATUS_READY, ChildProcessContainer::STATUS_ALIVE);
        foreach ($this->family as &$child) {

            if (in_array($child->status, $disposableStatusses)) {
                if ($child->getAge() < 10) {
                    //less than ten seconds old keep it alive a bit to let its do its job
                    continue;
                }
                $child->bedTime();
                return true;
            }
        }
        return false;
    }

    /**
     * Tells the children to stop what they are doing and go to bed
     *
     * @return boolean
     */
    private function tellChildrenToPrepareForBed()
    {
        foreach ($this->family as &$child) {
            $child->getReadyForBed();
        }
        return false;
    }

    /**
     * Spawns a new child
     *
     * @return Child
     */
    private function spawnChild()
    {
        $child = new ChildProcessContainer(
            $this->consolePath,
            $this->input->getOption('worker-command'),
            $this->tube,
            $this
        );
        $child->start();
        return $child;
    }
}
